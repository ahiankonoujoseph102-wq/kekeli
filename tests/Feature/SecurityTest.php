<?php

namespace Tests\Feature;

use App\Agent\PreviewToken;
use App\Agent\ProjectBackup;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Jobs\EraseProject;
use App\Jobs\WakePreview;
use App\Models\AgentPermission;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const URL = 'https://4100-iabc123def456.e2b.app';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
        ]);
        Storage::fake('local');
    }

    // Aperçu privé

    public function test_the_preview_token_is_the_same_as_in_the_runner(): void
    {
        // Valeurs calculées avec runner/src/apercu/serveur.mjs (cleApercu et jetonApercu).
        $this->assertSame('33ac9cabd56d5b044cb7af69693d55f0d966223fc12f5860d352426cc9a278ca', PreviewToken::key(self::SECRET, '01jtestprojet0000000000000'));
        $this->assertSame('1794200000.e0e5da5cc358b03d70f6cdf9034e90e0', PreviewToken::make(self::SECRET, '01jtestprojet0000000000000', 1794200000));
    }

    public function test_the_frame_opens_the_preview_with_a_private_token_that_stays_the_same(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00', 'UTC'));
        [$user, $project] = $this->project();
        $this->livePreview($project, 30);

        $expected = self::URL.'/k/'.PreviewToken::make(self::SECRET, $project->id, now()->addMinutes(30)->getTimestamp() + PreviewToken::MARGIN_SECONDS).'/';
        $this->assertSame($expected, PreviewToken::url($project->fresh()));

        $page = $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
        $page->assertSee('src="'.$expected.'"', false)->assertDontSee('src="'.self::URL.'"', false);

        // La page se rafraîchit chaque minute : l'adresse ne change pas, le cadre ne se recharge donc pas.
        $this->travel(1)->minutes();
        $this->actingAs($user)->get(route('projects.show', $project))->assertSee('src="'.$expected.'"', false);
    }

    public function test_another_user_cannot_see_the_project_or_its_preview(): void
    {
        [, $project] = $this->project();
        $this->livePreview($project, 30);

        $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_an_administrator_can_look_at_a_project_and_its_preview_without_changing_it(): void
    {
        [$owner, $project] = $this->project();
        $this->livePreview($project, 30);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('data-admin-view', false)
            ->assertSee($owner->email)
            ->assertSee('/k/', false)
            ->assertDontSee('wire:submit="send"', false)
            ->assertDontSee('data-open-sites', false);

        Volt::actingAs($admin)->test('projects.show', ['project' => $project])
            ->set('content', 'Supprime tout')
            ->call('send')
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    public function test_only_an_administrator_sees_the_list_of_all_projects(): void
    {
        [$owner, $project] = $this->project();

        $this->actingAs($owner)->get(route('admin.projects'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.projects'))
            ->assertOk()
            ->assertSee($project->name)
            ->assertSee($owner->email);
    }

    // Pause annoncée, puis rallumage

    public function test_kekeli_announces_the_pause_in_the_last_minute_and_a_half(): void
    {
        [$user, $project] = $this->project();
        $this->livePreview($project, 30);

        // Le minuteur de la page bascule tout seul 90 secondes avant la pause.
        $html = $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-preview-pausing-soon', false)
            ->getContent();
        $this->assertSame(1, preg_match('/bientot = true \}, (\d+)\)/', $html, $timer));
        $this->assertEqualsWithDelta((30 * 60 - 90) * 1000, (int) $timer[1], 2000);

        $project->forceFill(['preview_expires_at' => now()->addSeconds(60)])->save();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-preview="pausing"', false)
            ->assertSee('Kekeli se met en pause…')
            ->assertDontSee('<iframe', false);
    }

    public function test_a_paused_preview_can_be_turned_back_on_without_a_request(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->subMinute()])->save();

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-preview="asleep"', false)
            ->assertSee('Rallumer l&#039;aperçu', false);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('wakePreview');

        Queue::assertPushed(WakePreview::class, fn (WakePreview $job) => $job->project->is($project));
        $this->assertTrue($project->fresh()->previewIsWaking());
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-preview="waking"', false)
            ->assertSee('wire:poll.2s', false);

        // Le Runner a réveillé l'ordinateur : l'aperçu revient.
        $this->projectEvent($project, ['type' => 'apercu', 'url' => self::URL, 'expire_a' => now()->addMinutes(30)->toIso8601String()])->assertOk();
        $project->refresh();
        $this->assertTrue($project->hasLivePreview());
        $this->assertFalse($project->previewIsWaking());
    }

    public function test_the_runner_wakes_the_computer_with_the_sites_and_the_backup(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        [, $project] = $this->project();
        $project->sites()->create(['domain' => 'api.exemple.com']);
        $project->forceFill(['backup_at' => now()])->save();

        (new WakePreview($project))->handle(app(RunnerClient::class));

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/apercus'
            && $request['projet'] === $project->id
            && $request['rappel'] === route('runner.project-events', $project)
            && $request['sites'] === ['api.exemple.com']
            && $request['sauvegarde']['telechargement'] === route('runner.backup.show', $project));
    }

    public function test_a_failed_wake_up_lets_the_user_try_again(): void
    {
        [, $project] = $this->project();
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->subMinute(), 'preview_waking_at' => now()])->save();

        $this->projectEvent($project, ['type' => 'apercu_impossible'])->assertOk();

        $this->assertFalse($project->fresh()->previewIsWaking());
    }

    public function test_project_events_must_be_signed(): void
    {
        [, $project] = $this->project();
        $body = json_encode(['evenement' => ['type' => 'apercu', 'url' => self::URL]]);

        $this->call('POST', route('runner.project-events', $project), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $body)
            ->assertUnauthorized();
    }

    // Permissions

    public function test_the_user_allows_or_refuses_a_risky_action(): void
    {
        Http::fake(['runner.test/*' => Http::response(['ok' => true])]);
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);
        $user = $run->project->user;

        $this->event($run, ['type' => 'permission', 'id' => 'per_1', 'outil' => 'bash', 'cible' => 'rm -rf images'])->assertOk();
        $this->event($run, ['type' => 'permission', 'id' => 'per_2', 'outil' => 'bash', 'cible' => 'curl https://exemple.com'])->assertOk();
        $this->assertSame(2, AgentPermission::where('status', AgentPermission::STATUS_PENDING)->count());

        $this->actingAs($user)->get(route('projects.show', $run->project))
            ->assertSee('Kekeli demande votre permission')
            ->assertSee('Lancer une commande')
            ->assertSee('rm -rf images')
            ->assertSee('data-allow', false)
            ->assertSee('data-refuse', false);

        [$first, $second] = AgentPermission::orderBy('id')->get()->all();

        Volt::actingAs($user)->test('projects.show', ['project' => $run->project])
            ->call('answerPermission', $first->id, false)
            ->call('answerPermission', $second->id, true)
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/permissions'
            && $request['projet'] === $run->project_id && $request['permission'] === 'per_1' && $request['decision'] === 'refuser'
            && RunnerSignature::verify(self::SECRET, $request->header('X-Kekeli-Horodatage')[0], $request->header('X-Kekeli-Signature')[0], $request->body()));
        Http::assertSent(fn (Request $request) => $request['permission'] === 'per_2' && $request['decision'] === 'autoriser');

        $this->assertSame(AgentPermission::STATUS_REFUSED, $first->fresh()->status);
        $this->assertSame(AgentPermission::STATUS_ALLOWED, $second->fresh()->status);
        $this->assertSame($user->id, $first->fresh()->decided_by);

        // Le Runner confirme : rien ne change, la réponse est déjà enregistrée.
        $this->event($run, ['type' => 'permission_reglee', 'id' => 'per_1', 'decision' => 'refuser'])->assertOk();
        $this->assertSame(AgentPermission::STATUS_REFUSED, $first->fresh()->status);

        $this->actingAs($user)->get(route('projects.show', $run->project))
            ->assertSee('Permission refusée')
            ->assertSee('Permission accordée');
    }

    public function test_without_an_answer_the_action_is_refused(): void
    {
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'permission', 'id' => 'per_1', 'outil' => 'bash', 'cible' => 'git push'])->assertOk();
        $this->event($run, ['type' => 'permission_reglee', 'id' => 'per_1', 'decision' => 'expiree'])->assertOk();

        $this->assertSame(AgentPermission::STATUS_EXPIRED, AgentPermission::sole()->status);
    }

    public function test_an_unreachable_runner_keeps_the_question_open(): void
    {
        Http::fake(['runner.test/*' => Http::response(['ok' => false], 404)]);
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'permission', 'id' => 'per_1', 'outil' => 'bash', 'cible' => 'git push']);

        Volt::actingAs($run->project->user)->test('projects.show', ['project' => $run->project])
            ->call('answerPermission', AgentPermission::sole()->id, true)
            ->assertHasErrors('permission');

        $this->assertTrue(AgentPermission::sole()->isPending());
    }

    public function test_nobody_else_can_answer_a_permission(): void
    {
        Http::fake();
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'permission', 'id' => 'per_1', 'outil' => 'bash', 'cible' => 'git push']);
        [$otherUser, $otherProject] = $this->project();

        Volt::actingAs($this->admin())->test('projects.show', ['project' => $run->project])
            ->call('answerPermission', AgentPermission::sole()->id, true)
            ->assertForbidden();

        // Depuis son propre projet, un autre utilisateur ne trouve pas la permission d'un autre projet.
        Volt::actingAs($otherUser)->test('projects.show', ['project' => $otherProject])
            ->call('answerPermission', AgentPermission::sole()->id, true)
            ->assertNotFound();

        Http::assertNothingSent();
        $this->assertTrue(AgentPermission::sole()->isPending());
    }

    // Sites autorisés

    public function test_the_user_adds_and_removes_allowed_sites_and_every_change_is_kept(): void
    {
        [$user, $project] = $this->project();

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('newSite', ' https://API.Exemple.com/chemin ')
            ->call('addSite')
            ->assertHasNoErrors()
            ->assertSet('newSite', '');

        $this->assertSame(['api.exemple.com'], $project->allowedDomains());

        foreach (['pas un site', 'localhost', '192.168.1.1', '-exemple.com', 'images.unsplash.com', 'api.exemple.com'] as $invalid) {
            $component->set('newSite', $invalid)->call('addSite')->assertHasErrors('newSite');
        }

        $site = $project->sites()->sole();
        $component->call('removeSite', $site->id);

        $this->assertSame([], $project->allowedDomains());
        $this->assertNotNull($site->fresh()->removed_at);
        $this->assertSame($user->id, $site->user_id);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Historique des changements')
            ->assertSee('api.exemple.com ajouté')
            ->assertSee('api.exemple.com retiré');
    }

    public function test_each_request_sends_the_sites_the_backup_and_the_last_exchanges(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        [$user, $project] = $this->project();
        $project->sites()->create(['domain' => 'api.exemple.com']);
        $project->sites()->create(['domain' => 'ancien.exemple.com', 'removed_at' => now()]);
        $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une page d’accueil']);
        $project->messages()->create(['role' => Message::ROLE_ASSISTANT, 'content' => 'La page est créée.']);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Ajoute une page contact')
            ->call('send');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches'
            && $request['sites'] === ['api.exemple.com']
            && $request['sauvegarde']['envoi'] === route('runner.backup.store', $project)
            && $request['sauvegarde']['telechargement'] === null
            && $request['historique'] === [
                ['role' => 'user', 'contenu' => 'Crée une page d’accueil'],
                ['role' => 'assistant', 'contenu' => 'La page est créée.'],
            ]);
    }

    public function test_the_default_sites_are_the_same_as_in_the_runner(): void
    {
        $code = preg_replace('#//[^\n]*#', '', file_get_contents(base_path('runner/src/reseau.js')));
        $list = function (string $name) use ($code): array {
            preg_match('/export const '.$name.' = \[(.*?)\]/s', $code, $match);
            preg_match_all("/'([^']+)'/", $match[1], $domains);

            return $domains[1];
        };

        $this->assertSame($list('SITES_SYSTEME'), config('kekeli.default_sites.system'));
        $this->assertSame($list('SITES_LIBRES'), config('kekeli.default_sites.free'));
    }

    // Sauvegardes

    public function test_the_runner_sends_the_backup_in_parts_and_fetches_it_back(): void
    {
        [, $project] = $this->project();
        $this->getBackup($project)->assertNotFound();

        $this->postBackupPart($project, 1, 2, 'premier-morceau|')->assertOk()->assertJson(['complete' => false]);
        $this->assertNull($project->fresh()->backup_at);

        $this->postBackupPart($project, 2, 2, 'second-morceau')->assertOk()->assertJson(['complete' => true]);
        $project->refresh();
        $this->assertNotNull($project->backup_at);
        $this->assertSame(strlen('premier-morceau|second-morceau'), $project->backup_size);

        $response = $this->getBackup($project)->assertOk();
        $this->assertSame('premier-morceau|second-morceau', $response->streamedContent());
        $this->assertSame([], Storage::disk('local')->directories('sauvegardes'));
    }

    public function test_backups_must_be_signed_and_not_too_big(): void
    {
        [, $project] = $this->project();

        $this->call('POST', route('runner.backup.store', $project).'?partie=1&parties=1', content: 'abc')->assertUnauthorized();
        $this->call('GET', route('runner.backup.show', $project))->assertUnauthorized();

        config(['kekeli.backups.part_size' => 10]);
        $this->postBackupPart($project, 1, 1, str_repeat('x', 11))->assertStatus(413);
        $this->postBackupPart($project, 2, 1, 'x')->assertUnprocessable();
    }

    public function test_a_deleted_project_is_erased_with_its_computer_and_its_backup(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();

        Volt::actingAs($user)->test('projects.index')
            ->call('confirmDeletion', $project->id)
            ->call('deleteProject');

        Queue::assertPushed(EraseProject::class, fn (EraseProject $job) => $job->projectId === $project->id);

        Http::fake(['runner.test/*' => Http::response(['effaces' => 1])]);
        Storage::disk('local')->put('sauvegardes/'.$project->id.'.tar.gz', 'contenu');
        (new EraseProject($project->id))->handle(app(RunnerClient::class), app(ProjectBackup::class));

        Storage::disk('local')->assertMissing('sauvegardes/'.$project->id.'.tar.gz');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/projets/effacer' && $request['projet'] === $project->id);
    }

    // Étapes affichées

    public function test_resumption_licences_and_backup_steps_are_shown_in_french(): void
    {
        $run = $this->agentRun(['status' => AgentRun::STATUS_RUNNING]);

        foreach (['reprise', 'restauration', 'licences_ok', 'licences_probleme', 'sauvegarde', 'verification_correction', 'qualite_ok', 'qualite_probleme', 'photos'] as $kind) {
            $this->event($run, ['type' => 'etape', 'genre' => $kind, 'cible' => $kind === 'licences_ok' ? 'LICENCES.md' : ''])->assertOk();
        }

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))
            ->assertSee('Projet repris là où vous l&#039;aviez laissé', false)
            ->assertSee('Fichiers du projet remis en place depuis la sauvegarde')
            ->assertSee('Sources et licences vérifiées')
            ->assertSee('LICENCES.md')
            ->assertSee('Sources à vérifier à la main')
            ->assertSee('Projet sauvegardé')
            ->assertSee('Vérifications automatiques : l&#039;agent corrige', false)
            ->assertSee('Qualité vérifiée')
            ->assertSee('Points de qualité à revoir')
            ->assertSee('Recherche de photos libres');
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();

        return [$user, Project::factory()->for($user)->create()];
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->forceFill(['is_admin' => true])->save());
    }

    private function livePreview(Project $project, int $minutes): void
    {
        $project->forceFill(['preview_url' => self::URL, 'preview_expires_at' => now()->addMinutes($minutes)])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agentRun(array $attributes = []): AgentRun
    {
        [, $project] = $this->project();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une page d’accueil']);

        return tap($project->agentRuns()->create(['message_id' => $message->id]), fn (AgentRun $run) => $run->forceFill($attributes)->save());
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        return $this->signed('POST', '/runner/evenements', json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function projectEvent(Project $project, array $event): TestResponse
    {
        return $this->signed('POST', route('runner.project-events', $project), json_encode(['evenement' => $event], JSON_UNESCAPED_UNICODE));
    }

    private function postBackupPart(Project $project, int $part, int $parts, string $content): TestResponse
    {
        return $this->signed('POST', route('runner.backup.store', $project)."?partie={$part}&parties={$parts}", $content, 'application/gzip');
    }

    private function getBackup(Project $project): TestResponse
    {
        return $this->signed('GET', route('runner.backup.show', $project), '');
    }

    private function signed(string $method, string $uri, string $body, string $type = 'application/json'): TestResponse
    {
        $server = ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'];

        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, server: $server, content: $body);
    }
}
