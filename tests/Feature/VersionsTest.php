<?php

namespace Tests\Feature;

use App\Agent\ProjectBackup;
use App\Agent\ProjectFiles;
use App\Agent\RunnerClient;
use App\Agent\RunnerEventHandler;
use App\Agent\RunnerSignature;
use App\Agent\Tools\ModifySite;
use App\Github\GithubClient;
use App\Github\RepositoryClient;
use App\Jobs\ApplyProjectVersion;
use App\Jobs\PushProjectToGithub;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\User;
use App\Projects\ProjectVersions;
use App\Projects\TarArchive;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Versions et retour en arrière, comme Lovable et Bolt.new (docs/plan.md, partie 26.17).
 */
class VersionsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-des-versions-de-kekeli-2026';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    /** Une sauvegarde du projet, comme le Runner l'envoie (avec ce qui ne doit jamais entrer dans une version). */
    private function backup(Project $project, array $files): void
    {
        $tar = '';
        foreach ($files as $path => $content) {
            $tar .= TarArchive::entry('./'.$path, $content);
        }
        $backups = app(ProjectBackup::class);
        $backups->disk()->put($backups->path($project->id), gzencode($tar.str_repeat("\0", 1024)));
        $project->forceFill(['backup_at' => now()->addSecond()])->save();
    }

    private function site(User $user): Project
    {
        $project = $user->projects()->make(['name' => 'Restaurant Chez Ama']);
        $project->forceFill(['kind' => Project::KIND_SITE])->save();

        return $project;
    }

    /** Un travail réussi de Kekeli : sa sauvegarde, puis l'événement « terminee » du Runner. */
    private function work(Project $project, string $request, array $files, ?string $title = null): AgentRun
    {
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => $request]);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subMinute(), 'sent_at' => now()->subMinute(), 'version_title' => $title, 'changes' => [['chemin' => 'index.html']]])->save();
        $this->backup($project, $files);
        app(RunnerEventHandler::class)->handle($run->fresh(), ['type' => 'terminee', 'reponse' => 'C’est fait.']);

        return $run->fresh();
    }

    private const V1 = ['index.html' => '<h1>Chez Ama</h1>', '.env' => 'APP_KEY=base64:jamais-dans-une-version', 'database/database.sqlite' => 'base d’essai', 'node_modules/paquet/index.js' => 'dépendance', 'config.js' => 'const cle = "sk-proj-abcdefghijklmnopqrstuvwxyz0123456789"', '.env.example' => 'APP_KEY='];

    public function test_each_successful_work_creates_a_version_without_any_secret_and_the_discussion_says_it(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);

        $first = $this->work($project, 'Un site pour mon restaurant', self::V1);
        $second = $this->work($project, 'Ajoute une page contact', self::V1 + ['contact.html' => '<h1>Contact</h1>'], 'Ajout de la page Contact');
        // Un travail qui ne change aucun fichier ne crée pas de version.
        $this->work($project, 'Vérifie le site', self::V1 + ['contact.html' => '<h1>Contact</h1>']);

        $versions = $project->versions()->get();
        $this->assertSame([1, 2], $versions->pluck('number')->all());
        $this->assertSame(['Création du site', 'Ajout de la page Contact'], $versions->pluck('title')->all());
        $this->assertSame([1, 2], [$first->report['version'], $second->report['version']]);

        $files = app(ProjectVersions::class)->files(Storage::disk('local')->get(app(ProjectVersions::class)->path($versions->last())));
        $this->assertSame(['.env.example', 'contact.html', 'index.html'], array_keys($files), 'jamais .env, base d’essai, dépendances ni fichier avec une clé');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->assertSee('Version 2 créée')->assertSee('Version 1 créée')
            ->assertSee('data-versions-button', false)
            ->assertSee('Ajout de la page Contact')
            ->assertSee('Actuelle');
    }

    public function test_an_old_version_is_seen_in_the_preview_without_changing_anything_then_back_to_the_current(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        $this->work($project, 'Un site', self::V1);
        $this->work($project, 'Ajoute une page contact', self::V1 + ['contact.html' => 'Contact'], 'Ajout de la page Contact');

        $page = Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('viewVersion', 1);
        Queue::assertPushed(ApplyProjectVersion::class, fn ($job) => $job->action === 'voir' && $job->number === 1);
        $this->assertSame(1, $project->fresh()->viewing_version);
        $this->assertSame(2, $project->versions()->count(), 'regarder ne crée ni n’efface rien');

        $page = Volt::actingAs($user)->test('projects.show', ['project' => $project->fresh()])
            ->assertSee('Vous regardez la version 1')->assertSee('Revenir à la version actuelle')->assertSee('Restaurer cette version')
            ->set('content', 'Change le titre')->call('send')
            ->assertSee('Vous regardez une ancienne version.');
        $this->assertSame(0, AgentRun::query()->where('status', AgentRun::STATUS_QUEUED)->count());

        $page->call('backToCurrent');
        Queue::assertPushed(ApplyProjectVersion::class, fn ($job) => $job->action === 'revenir');
        $this->assertNull($project->fresh()->viewing_version);
    }

    public function test_restoring_creates_a_new_version_and_a_restoration_can_be_undone(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        $this->work($project, 'Un site', self::V1);
        $this->work($project, 'Ajoute une page contact', self::V1 + ['contact.html' => 'Contact'], 'Ajout de la page Contact');
        $this->work($project, 'Ajoute une page menu', self::V1 + ['contact.html' => 'Contact', 'menu.html' => 'Menu'], 'Ajout de la page Menu');

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->call('askRestore', 2)
            ->assertSee('Restaurer cette version ?')
            ->assertSee('Kekeli crée la version 4 avec ces fichiers')
            ->call('restoreVersion');

        $restored = $project->versions()->where('number', 4)->sole();
        $this->assertSame([ProjectVersion::KIND_RESTORE, 'Retour à la version 2', 2], [$restored->kind, $restored->title, $restored->restored_from]);
        $versions = app(ProjectVersions::class);
        $disk = Storage::disk('local');
        $this->assertSame($versions->files($disk->get($versions->path($project->versions()->where('number', 2)->sole()))), $versions->files($disk->get($versions->path($restored))));
        Queue::assertPushed(ApplyProjectVersion::class, fn ($job) => $job->action === 'remettre' && $job->number === 4);
        $this->assertSame(4, $project->versions()->count(), 'rien n’est effacé');
        $this->assertStringContainsString('Version 4 · Retour à la version 2 créée', (string) $project->messages()->reorder('id', 'desc')->value('content'));

        // Annuler la restauration : restaurer la version 3, qui crée la version 5.
        Volt::actingAs($user)->test('projects.show', ['project' => $project->fresh()])->call('askRestore', 3)->call('restoreVersion');
        $this->assertSame('Retour à la version 3', $project->versions()->where('number', 5)->value('title'));

        // Restaurer ne consomme aucune énergie de modèle : aucune requête au premier répondant ni à l'agent.
        $this->assertSame([], $this->model->received);
        Queue::assertNotPushed(SendAgentRun::class);
    }

    public function test_a_failed_or_stopped_work_leaves_the_project_on_its_last_version_that_worked(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        $this->work($project, 'Un site', self::V1);

        foreach ([['type' => 'erreur', 'raison' => 'interne', 'detail' => 'panne'], ['type' => 'arretee', 'raison' => 'client']] as $event) {
            $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Refais tout le site']);
            $run = $project->agentRuns()->create(['message_id' => $message->id]);
            $run->forceFill(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now(), 'changes' => [['chemin' => 'index.html']]])->save();
            $this->backup($project, ['index.html' => 'à moitié refait']);
            app(RunnerEventHandler::class)->handle($run->fresh(), $event);
            $this->assertSame(1, $run->fresh()->report['kept_version']);
        }

        $this->assertSame(1, $project->versions()->count(), 'aucune version pour un travail échoué ou arrêté');
        Queue::assertPushed(ApplyProjectVersion::class, fn ($job) => $job->action === 'remettre' && $job->number === 1);
        Volt::actingAs($user)->test('projects.show', ['project' => $project])->assertSee('Le projet est resté sur la version 1, la dernière qui fonctionnait.')
            ->assertSee('Vous avez arrêté le travail.')->assertDontSee('Ce qui était déjà fait est gardé');
    }

    public function test_the_runner_gets_the_signed_archive_of_a_version_and_the_preview_follows(): void
    {
        Queue::fake();
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        $this->work($project, 'Un site', self::V1);

        app(RunnerClient::class)->applyVersion($project->fresh(), 'voir', 1);
        $archive = null;
        Http::assertSent(function (Request $request) use (&$archive) {
            $archive = $request['version']['archive'] ?? null;

            return str_ends_with($request->url(), '/apercus') && $request['version']['action'] === 'voir' && $request['version']['numero'] === 1;
        });

        $this->get($archive)->assertUnauthorized();
        $server = [];
        foreach (RunnerSignature::headers(self::SECRET, '') as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $response = $this->call('GET', $archive, server: $server)->assertOk();
        $this->assertArrayHasKey('index.html', app(ProjectVersions::class)->files($response->streamedContent()));

        $project->forceFill(['version_busy_at' => now()])->save();
        app(RunnerEventHandler::class)->handleProject($project, ['type' => 'version_appliquee', 'action' => 'voir', 'numero' => 1]);
        $this->assertNull($project->fresh()->version_busy_at);
    }

    public function test_a_restoration_goes_to_github_as_a_new_commit_never_with_a_forced_push(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG', 'github_token' => 'ghu_jeton', 'github_token_expires_at' => now()->addHours(8)]);
        config(['services.github.app_slug' => 'kekeli-essai', 'services.github.client_id' => 'Iv1.essai', 'services.github.client_secret' => 'secret-essai']);
        $project = $this->site($user);
        $project->forceFill(['repository' => 'ama/restaurant', 'github_repo' => 'ama/restaurant', 'repository_base_branch' => 'main', 'github_push_mode' => Project::PUSH_DIRECT])->save();
        $this->work($project, 'Un site', self::V1);
        $this->work($project, 'Ajoute une page contact', self::V1 + ['contact.html' => 'Contact'], 'Ajout de la page Contact');

        app(ProjectVersions::class)->restore($project->fresh(), $project->versions()->where('number', 1)->sole());
        Queue::assertPushed(PushProjectToGithub::class, fn ($job) => $job->restoredVersion === 3);

        Http::fake([
            'api.github.com/repos/ama/restaurant/git/ref/heads/main' => Http::response(['object' => ['sha' => 'commit-2']]),
            'api.github.com/repos/ama/restaurant/git/commits/commit-2' => Http::response(['tree' => ['sha' => 'arbre-2']]),
            'api.github.com/repos/ama/restaurant/git/trees/arbre-2*' => Http::response(['tree' => [['path' => 'index.html', 'type' => 'blob', 'sha' => 'ancien', 'mode' => '100644'], ['path' => 'contact.html', 'type' => 'blob', 'sha' => 'contact', 'mode' => '100644']]]),
            'api.github.com/repos/ama/restaurant/git/blobs' => Http::response(['sha' => 'blob']),
            'api.github.com/repos/ama/restaurant/git/trees' => Http::response(['sha' => 'arbre-3']),
            'api.github.com/repos/ama/restaurant/git/commits' => Http::response(['sha' => 'commit-3']),
            'api.github.com/repos/ama/restaurant/git/refs/heads/main' => Http::response(['object' => ['sha' => 'commit-3']]),
            '*' => Http::response([]),
        ]);
        (new PushProjectToGithub($project->fresh(), null, 3))->handle(app(ProjectFiles::class), app(GithubClient::class), app(RepositoryClient::class));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/commits') && $request['message'] === 'Retour à la version 1' && $request['parents'] === ['commit-2']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/trees') && collect($request['tree'])->contains(fn ($entry) => $entry['path'] === 'contact.html' && $entry['sha'] === null));
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request['force'] === false);
        Http::assertNotSent(fn (Request $request) => ($request['force'] ?? false) === true);
        Http::assertNotSent(fn (Request $request) => str_contains($request->body(), 'jamais-dans-une-version'));
    }

    public function test_old_versions_are_pruned_beyond_the_limit_but_never_the_current_nor_a_restored_one(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        app(Settings::class)->set('versions_kept_free', 5);
        foreach (range(1, 4) as $i) {
            $this->work($project, 'Changement '.$i, ['index.html' => 'contenu '.$i], 'Changement '.$i);
        }
        app(ProjectVersions::class)->restore($project->fresh(), $project->versions()->where('number', 1)->sole());
        foreach (range(6, 8) as $i) {
            $this->work($project, 'Changement '.$i, ['index.html' => 'contenu '.$i], 'Changement '.$i);
        }

        $kept = $project->versions()->pluck('number')->all();
        $this->assertCount(5, $kept);
        $this->assertContains(1, $kept, 'la version restaurée reste');
        $this->assertContains(5, $kept, 'la version créée par la restauration reste');
        $this->assertContains(8, $kept, 'la version actuelle reste');
        $this->assertFalse(Storage::disk('local')->exists('versions/'.$project->id.'/2.tar.gz'));
    }

    public function test_the_first_responder_gives_the_short_title_of_the_version(): void
    {
        Queue::fake();
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->site($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Ajoute une page contact avec un formulaire']);
        $responder = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $responder->forceFill(['status' => AgentRun::STATUS_COMPLETED])->save();

        $run = app(ModifySite::class)->handle($responder, ['version' => '« Ajout de la page Contact »']);

        $this->assertSame('Ajout de la page Contact', $run->version_title);
    }
}
