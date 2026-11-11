<?php

namespace Tests\Feature;

use App\Agent\ProviderList;
use App\Agent\RunnerSignature;
use App\Jobs\SendAgentRun;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini,openrouter,mistral',
        ]);
    }

    public function test_the_provider_list_follows_the_settings(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'UTC'));
        config(['kekeli.providers_order' => ' gemini/gemini-3.1-flash-lite, inconnu, gemini, openrouter/nvidia/modele:free, gemini, mistral ']);

        $list = app(ProviderList::class)->forRunner();
        $this->assertSame('2026-11-03T08:00:00+00:00', $list[0]['remise_a_zero']);
        $this->assertSame('2026-11-03T00:00:00+00:00', $list[2]['remise_a_zero']);

        $this->assertSame([
            ['fournisseur' => 'gemini', 'modele' => 'gemini-3.1-flash-lite'],
            ['fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite'],
            ['fournisseur' => 'openrouter', 'modele' => 'nvidia/modele:free'],
            ['fournisseur' => 'mistral', 'modele' => 'devstral-latest'],
        ], array_map(fn ($entry) => array_diff_key($entry, ['remise_a_zero' => true]), $list));
    }

    public function test_groq_is_in_the_list_and_a_paid_openrouter_model_is_never_used(): void
    {
        config(['kekeli.providers_order' => 'gemini,groq,openrouter/anthropic/claude-sonnet-5.5,openrouter/openrouter/free,openrouter']);

        $this->assertSame(
            ['gemini/gemini-3.5-flash-lite', 'groq/openai/gpt-oss-120b', 'openrouter/google/gemma-4-31b-it:free'],
            array_column(app(ProviderList::class)->entries(), 'key'),
        );

        // Même le modèle par défaut d'OpenRouter est refusé s'il est réglé sur un modèle payant.
        config(['kekeli.providers_order' => 'openrouter', 'kekeli.providers.openrouter.model' => 'openai/gpt-oss-120b']);
        $this->assertSame([], app(ProviderList::class)->entries());
        $this->assertTrue(ProviderList::isAllowed('openrouter', 'cohere/north-mini-code:free'));
        $this->assertFalse(ProviderList::isAllowed('openrouter', 'cohere/north-mini-code'));
    }

    public function test_the_model_of_a_provider_is_a_setting(): void
    {
        config(['kekeli.providers_order' => 'gemini', 'kekeli.providers.gemini.model' => 'autre-modele']);

        $this->assertSame('autre-modele', app(ProviderList::class)->forRunner()[0]['modele']);
    }

    public function test_sending_a_message_sends_a_signed_request_to_the_runner(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        [$user, $project] = $this->project();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Crée un fichier bonjour.txt')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('content', '');

        $run = AgentRun::sole();
        $this->assertSame(AgentRun::STATUS_QUEUED, $run->status);
        $this->assertSame('Crée un fichier bonjour.txt', $run->message->content);
        $this->assertSame(Message::ROLE_USER, $run->message->role);

        Http::assertSent(function (Request $request) use ($run, $project) {
            $data = $request->data();

            return $request->url() === 'http://runner.test/taches'
                && RunnerSignature::verify(self::SECRET, $request->header('X-Kekeli-Horodatage')[0], $request->header('X-Kekeli-Signature')[0], $request->body())
                && $data['tache'] === $run->id
                && $data['projet'] === $project->id
                && $data['message'] === 'Crée un fichier bonjour.txt'
                && $data['reprise'] === false
                && $data['fournisseurs'][0]['fournisseur'] === 'gemini'
                && $data['fournisseurs'][0]['modele'] === 'gemini-3.5-flash-lite'
                && $data['rappel'] === route('runner.events')
                && ! str_contains($request->body(), self::SECRET);
        });
    }

    public function test_a_message_is_required_and_only_one_request_runs_at_a_time(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', '   ')
            ->call('send')
            ->assertHasErrors(['content' => 'required']);

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Première demande')
            ->call('send')
            ->set('content', 'Deuxième demande')
            ->call('send')
            ->assertHasErrors('content')
            ->assertSee('Kekeli travaille encore sur votre demande précédente.');

        $this->assertSame(1, AgentRun::count());
        Queue::assertPushed(SendAgentRun::class, 1);
    }

    public function test_the_runner_unreachable_marks_the_request_as_failed_and_alerts_the_administrator(): void
    {
        Http::fake(['runner.test/*' => Http::response(['erreur' => 'panne'], 500)]);
        [$user, $project] = $this->project();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Bonjour')
            ->call('send')
            ->assertSee("Désolé, Kekeli n'a pas pu terminer cette demande.");

        $this->assertSame(AgentRun::STATUS_FAILED, AgentRun::sole()->status);
        $this->assertSame('runner_unreachable', AdminAlert::sole()->kind);
    }

    public function test_events_without_a_valid_signature_are_refused(): void
    {
        $run = $this->agentRun();
        $body = json_encode(['tache' => $run->id, 'evenement' => ['type' => 'demarrage']]);

        $this->call('POST', '/runner/evenements', server: $this->jsonServer(), content: $body)->assertUnauthorized();
        $this->call('POST', '/runner/evenements', server: $this->jsonServer([
            'HTTP_X_KEKELI_HORODATAGE' => (string) now()->timestamp,
            'HTTP_X_KEKELI_SIGNATURE' => RunnerSignature::sign('mauvais-secret', (string) now()->timestamp, $body),
        ]), content: $body)->assertUnauthorized();

        $old = (string) now()->subMinutes(10)->timestamp;
        $this->call('POST', '/runner/evenements', server: $this->jsonServer([
            'HTTP_X_KEKELI_HORODATAGE' => $old,
            'HTTP_X_KEKELI_SIGNATURE' => RunnerSignature::sign(self::SECRET, $old, $body),
        ]), content: $body)->assertUnauthorized();

        $this->assertSame(AgentRun::STATUS_QUEUED, $run->fresh()->status);
        $this->event($run, ['type' => 'demarrage'])->assertOk();
        $this->assertSame(AgentRun::STATUS_RUNNING, $run->fresh()->status);
    }

    public function test_the_user_watches_the_agent_work_live_and_requests_are_counted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 07:30', 'UTC'));
        $run = $this->agentRun();

        $this->event($run, ['type' => 'demarrage']);
        $this->event($run, ['type' => 'fournisseur', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite']);
        $this->event($run, ['type' => 'etape', 'genre' => 'fichier_ecrit', 'cible' => 'bonjour.txt']);
        $this->event($run, ['type' => 'requete', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 8000, 'tokens_sortie' => 30, 'tokens_reflexion' => 800, 'tokens_cache' => 0]);
        $this->event($run, ['type' => 'etape', 'genre' => 'commande', 'cible' => 'cat bonjour.txt']);
        $this->event($run, ['type' => 'requete', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 9000, 'tokens_sortie' => 20]);
        $this->event($run, ['type' => 'refus', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite']);
        $this->event($run, ['type' => 'genre-inconnu']);

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))
            ->assertOk()
            ->assertSee('wire:poll.2s', false)
            ->assertSee('Kekeli travaille…')
            ->assertSee('Fichier créé')
            ->assertSee('Travail technique')
            // Des libellés simples : ni commande ni chemin de fichier dans les actions.
            ->assertDontSee('cat bonjour.txt')
            ->assertDontSee('gemini');

        // Gemini compte sa journée à l'heure du Pacifique : à 7 h 30 à Lomé, c'est encore le 1er novembre.
        $usage = ProviderUsage::sole();
        $this->assertSame('2026-11-01', $usage->day->toDateString());
        $this->assertSame([2, 1, 17000, 50, 800], [$usage->requests, $usage->rate_limited, $usage->input_tokens, $usage->output_tokens, $usage->reasoning_tokens]);

        $this->event($run, ['type' => 'terminee', 'reponse' => "J'ai créé le fichier bonjour.txt."]);

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->finished_at);

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))
            // Une réponse qui ne contient qu'un nom de fichier devient une phrase simple.
            ->assertSee('C&#039;est fait : votre site est à jour.', false)
            ->assertSee('Kekeli a fait 2 actions')
            ->assertDontSee('wire:poll.2s', false)
            ->assertDontSee('Kekeli travaille…');
    }

    public function test_when_every_provider_is_exhausted_the_request_waits_and_resumes_automatically(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 05:00', 'UTC'));
        $run = $this->agentRun();

        $this->event($run, ['type' => 'en_attente', 'reprise_vers' => '2026-11-02T08:00:00Z'])->assertOk();

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_WAITING, $run->status);
        $this->assertTrue($run->resume_at->equalTo(CarbonImmutable::parse('2026-11-02 08:00', 'UTC')));

        Queue::assertPushed(SendAgentRun::class, fn (SendAgentRun $job) => $job->run->is($run) && $job->resume
            && $job->delay->equalTo(CarbonImmutable::parse('2026-11-02 08:00:30', 'UTC')));

        $alert = AdminAlert::sole();
        $this->assertSame('providers_exhausted', $alert->kind);
        $this->assertStringContainsString('08:00', $alert->message);

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))
            ->assertSee('Kekeli est très sollicité en ce moment. Votre demande est en attente et reprendra automatiquement vers 08:00.')
            ->assertDontSee('gratuit')
            ->assertDontSee('fournisseur');
    }

    public function test_the_waiting_request_is_sent_again_with_the_resume_flag(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $run = $this->agentRun(['status' => AgentRun::STATUS_WAITING]);

        SendAgentRun::dispatch($run, true);

        Http::assertSent(fn (Request $request) => $request->data()['reprise'] === true && $request->data()['tache'] === $run->id);

        // Une reprise n'est jamais envoyée pour une demande qui n'attend plus.
        Http::fake();
        SendAgentRun::dispatch($this->agentRun(['status' => AgentRun::STATUS_COMPLETED]), true);
        SendAgentRun::dispatch($this->agentRun(['status' => AgentRun::STATUS_RUNNING]), true);
        Http::assertNothingSent();
    }

    public function test_an_error_is_shown_simply_to_the_user_and_detailed_to_the_administrator(): void
    {
        $run = $this->agentRun();

        $this->event($run, ['type' => 'erreur', 'raison' => 'agent', 'detail' => 'Modèle introuvable'])->assertOk();

        $this->assertSame(AgentRun::STATUS_FAILED, $run->fresh()->status);
        $this->assertStringContainsString('Modèle introuvable', AdminAlert::sole()->message);

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))
            ->assertSee("Désolé, Kekeli n'a pas pu terminer cette demande. Réessayez dans un instant.")
            ->assertDontSee('Modèle introuvable');
    }

    public function test_events_for_an_unknown_request_are_rejected(): void
    {
        $body = json_encode(['tache' => '01JUNKNOWN000000000000000', 'evenement' => ['type' => 'demarrage']]);

        $this->call('POST', '/runner/evenements', server: $this->jsonServer(RunnerSignature::headers(self::SECRET, $body)), content: $body)
            ->assertNotFound();
    }

    public function test_only_administrators_see_the_providers_page(): void
    {
        $this->get('/administration/fournisseurs')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/administration/fournisseurs')->assertForbidden();

        Http::fake(['runner.test/etat' => Http::response(['cles_presentes' => ['gemini'], 'mis_de_cote' => []])]);
        $this->actingAs($this->admin())->get('/administration/fournisseurs')
            ->assertOk()
            ->assertSee("Fournisseurs d'IA");
    }

    public function test_the_providers_page_shows_usage_limits_and_alerts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'UTC'));
        config(['kekeli.providers_order' => 'gemini,openrouter,mistral']);

        $usage = ProviderUsage::create(['day' => '2026-11-02', 'provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite']);
        $usage->forceFill(['requests' => 410, 'rate_limited' => 3, 'input_tokens' => 1234567, 'output_tokens' => 5000])->save();
        AdminAlert::create(['kind' => 'providers_exhausted', 'message' => 'Tous les fournisseurs sont épuisés.']);

        Http::fake(['runner.test/etat' => Http::response([
            'cles_presentes' => ['gemini', 'openrouter'],
            'mis_de_cote' => ['openrouter/google/gemma-4-31b-it:free' => '2026-11-03T00:00:00Z'],
        ])]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/administration/fournisseurs')
            ->assertOk()
            ->assertSeeInOrder(['1. Google AI Studio (Gemini)', '2. OpenRouter', '3. Mistral'])
            ->assertSee('410')
            ->assertSee('/ 500')
            ->assertSee('Attention : 82 % de la limite du jour est utilisé.')
            ->assertSee('1 234 567')
            ->assertSee('Disponible')
            ->assertSee("Mis de côté jusqu'à 00:00")
            ->assertSee('Clé absente, ignoré')
            ->assertSee('Limite du jour non publiée par le fournisseur.')
            ->assertSee('08:00 (heure de Lomé)')
            ->assertSee('Tous les fournisseurs sont épuisés.');

        Volt::actingAs($admin)->test('admin.providers')
            ->call('markAllAsRead')
            ->assertSee('Aucune alerte.');

        $this->assertSame(0, AdminAlert::unread()->count());
    }

    public function test_the_providers_page_still_works_when_the_runner_is_unreachable(): void
    {
        Http::fake(['runner.test/*' => Http::response(null, 500)]);

        $this->actingAs($this->admin())->get('/administration/fournisseurs')
            ->assertOk()
            ->assertSee('Le Runner est injoignable')
            ->assertSee('État inconnu');
    }

    public function test_the_admin_command_gives_access_to_the_administration(): void
    {
        $user = User::factory()->create(['email' => 'joseph@exemple.tg']);

        $this->artisan('kekeli:admin', ['email' => 'joseph@exemple.tg'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('kekeli:admin', ['email' => 'personne@exemple.tg'])->assertFailed();
    }

    public function test_users_cannot_make_themselves_administrators(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true])->save();

        $this->assertFalse($user->fresh()->is_admin);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();

        return [$user, Project::factory()->for($user)->create()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agentRun(array $attributes = []): AgentRun
    {
        [, $project] = $this->project();
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée un fichier bonjour.txt']);

        return tap($project->agentRuns()->create(['message_id' => $message->id]), fn (AgentRun $run) => $run->forceFill($attributes)->save());
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->forceFill(['is_admin' => true])->save());
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        $body = json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/runner/evenements', server: $this->jsonServer(RunnerSignature::headers(self::SECRET, $body)), content: $body);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function jsonServer(array $headers = []): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server[str_starts_with($name, 'HTTP_') ? $name : 'HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    public function test_only_free_models_are_allowed_at_every_provider(): void
    {
        $this->assertTrue(ProviderList::isAllowed('workersai', '@cf/zai-org/glm-4.7-flash'));
        $this->assertFalse(ProviderList::isAllowed('workersai', '@cf/moonshotai/kimi-k2.6'));
        $this->assertFalse(ProviderList::isAllowed('workersai', '@cf/zai-org/glm-5.3'));
        $this->assertTrue(ProviderList::isAllowed('gemini', 'gemini-3.5-flash-lite'));
        $this->assertFalse(ProviderList::isAllowed('gemini', 'gemini-3.1-pro-preview'));
        $this->assertFalse(ProviderList::isAllowed('gemini', 'gemini-4-argon'));
        $this->assertFalse(ProviderList::isAllowed('openrouter', 'openai/gpt-oss-120b'));
        $this->assertTrue(ProviderList::isAllowed('openrouter', 'nvidia/nemotron-3-ultra-550b-a55b:free'));

        config([
            'kekeli.providers_order' => 'gemini,gemini/gemini-4-argon,workersai,workersai/@cf/moonshotai/kimi-k2.6,openrouter/cohere/north-mini-code:free',
        ]);
        $this->assertSame(
            ['gemini/gemini-3.5-flash-lite', 'workersai/@cf/zai-org/glm-4.7-flash', 'openrouter/cohere/north-mini-code:free'],
            array_column(app(ProviderList::class)->entries(), 'key'),
        );
    }

    public function test_the_default_list_follows_the_ranking_of_the_free_providers(): void
    {
        $default = 'gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai';
        $this->assertStringContainsString("env('KEKELI_FOURNISSEURS', '{$default}')", file_get_contents(config_path('kekeli.php')));
        config(['kekeli.providers_order' => $default]);

        $this->assertSame([
            'gemini/gemini-3.5-flash-lite',
            'gemini/gemini-3.1-flash-lite',
            'openrouter/google/gemma-4-31b-it:free',
            'workersai/@cf/zai-org/glm-4.7-flash',
        ], array_column(app(ProviderList::class)->entries(), 'key'));
        // Mistral (plus d'offre gratuite) et les modèles gratuits de NVIDIA et de Cohere ne sont pas dans la liste par défaut.
        $this->assertStringNotContainsString('mistral', $default);
        $this->assertStringNotContainsString('nvidia/', $default.config('kekeli.providers.openrouter.model'));
        $this->assertStringNotContainsString('cohere/', $default);
    }
}
