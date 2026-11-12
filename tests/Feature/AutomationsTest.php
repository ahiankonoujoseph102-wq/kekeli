<?php

namespace Tests\Feature;

use App\Agent\AgentTrial;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateAutomation;
use App\Jobs\LaunchAutomation;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Automatisations, comme Claude Code : Kekeli écrit le code et le fichier GitHub Actions planifié ; l'automatisation
 * tourne ensuite sur le dépôt GitHub du client, jamais chez Kekeli. « Lancer maintenant » la fait tourner une fois
 * dans l'ordinateur, avec un journal lisible. Le modèle et le Runner sont simulés.
 */
class AutomationsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const PLAN = "## Déclencheur\n- Chaque jour à 7 h (heure de Lomé)\n\n## Étapes\n1. Préparer trois conseils du jour\n2. Envoyer l'e-mail\n\n## Services utilisés\n- Votre modèle (votre clé)\n- Resend pour l'e-mail (votre clé)\n\n## Résultat attendu\n- Un court e-mail avec trois conseils\n\n## Envoyé à\n- Votre adresse e-mail\n\nTechnologie : JavaScript, avec GitHub Actions";

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendAgentRun::class, LaunchAutomation::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function automation(User $user, array $attributes = []): Project
    {
        $project = $user->projects()->make(['name' => 'Conseils du matin']);
        $project->forceFill($attributes + ['kind' => Project::KIND_WEBAPP, 'is_automation' => true, 'app_stack' => 'automatisation', 'app_template' => 'socle', 'backup_at' => now()])->save();

        return $project;
    }

    public function test_an_automation_request_shows_its_plan_with_trigger_steps_services_and_a_build_button(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_automatisation', ['plan' => self::PLAN, 'pile' => 'javascript', 'confirme' => 'non', 'nom' => 'Conseils du matin']);
        $chat = $this->ask($user, 'chaque matin à 7 h, envoie-moi par e-mail trois conseils du jour pour mon commerce');

        $this->assertTrue($chat->fresh()->isChat(), 'aucune construction avant le plan accepté');
        Bus::assertNotDispatched(SendAgentRun::class);
        $this->assertSame(CreateAutomation::OFFER, data_get($chat->agentRuns()->sole()->report, 'offer'));
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('<h2>Déclencheur</h2>', false)
            ->assertSee('<h2>Services utilisés</h2>', false)
            ->assertSee('<h2>Envoyé à</h2>', false)
            ->assertSee('data-build-plan', false)
            ->assertSee('Construire ce plan');
    }

    public function test_build_this_plan_turns_the_discussion_into_an_automation_and_starts_the_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_automatisation', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        $chat = $this->ask($user, 'tous les matins, un e-mail avec trois conseils pour mon commerce');

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateAutomation::class)->lastOffer($chat)->id);

        $project = $chat->fresh();
        $this->assertTrue($project->isAutomation());
        $this->assertFalse($project->isAgent());
        $this->assertSame('automatisation', $project->app_stack);
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertStringContainsString('AUTOMATISATION', $run->instructions);
        $this->assertStringContainsString('## Déclencheur', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        // Même modèle de construction que les applications ; l'essai gratuit du modèle sert aussi à « Lancer maintenant ».
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $request) => $request['application']['pile'] === 'automatisation'
            && str_contains((string) $request['essai_agent'], '/essai-agent')
            && $request['fournisseurs'][0]['modele'] === 'gpt-6.1-terra');
    }

    public function test_an_imposed_technology_is_respected(): void
    {
        $this->assertNull(CreateAutomation::imposedStack('auto', 'un e-mail chaque matin'));
        $this->assertNull(CreateAutomation::imposedStack('javascript', 'un e-mail chaque matin'));
        $this->assertSame('Python', CreateAutomation::imposedStack('python', ''));
        $this->assertSame('Python', CreateAutomation::imposedStack('auto', 'je la veux en Python'));
    }

    public function test_run_now_sends_the_secret_variables_and_only_the_needed_services_to_the_runner(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->automation($user);
        $project->secrets()->create(['name' => 'RESEND_API_KEY', 'value' => 're_cle_inventee_pour_le_test_0123456789']);
        $project->secrets()->create(['name' => 'EMAIL_DESTINATAIRE', 'value' => 'afi@exemple.tg']);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('launchNow');

        $run = $project->agentRuns()->sole();
        $this->assertTrue($run->isLaunch());
        $this->assertSame('Lancer maintenant', $run->message->content);
        Bus::assertDispatched(LaunchAutomation::class);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        (new LaunchAutomation($run))->handle(app(RunnerClient::class));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/lancer')
            && $request['tache'] === $run->id
            && $request['secrets']['EMAIL_DESTINATAIRE'] === 'afi@exemple.tg'
            && in_array('api.resend.com', $request['sites'], true)
            && $request['application']['pile'] === 'automatisation');
    }

    public function test_run_now_is_refused_for_a_project_that_is_not_an_automation(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->automation($user, ['is_automation' => false, 'is_agent' => true, 'app_stack' => 'langgraph']);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('launchNow');

        $this->assertSame(0, $project->agentRuns()->where('purpose', AgentRun::PURPOSE_LAUNCH)->count(), (string) $project->agentRuns()->get()->toJson());
    }

    public function test_the_log_appears_live_then_the_summary_and_no_secret_is_ever_shown(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->automation($user);
        $run = $project->agentRuns()->create(['message_id' => $project->messages()->create(['role' => 'user', 'content' => 'Lancer maintenant'])->id, 'purpose' => AgentRun::PURPOSE_LAUNCH]);

        $this->event($run, ['type' => 'demarrage'])->assertOk();
        $this->event($run, ['type' => 'journal', 'etape' => 'Préparer le contenu', 'statut' => 'ok', 'message' => 'Trois conseils prêts.'])->assertOk();
        $this->actingAs($user)->get(route('projects.show', $project).'?vue=apercu')->assertOk()
            ->assertSee('data-launch-panel', false)
            ->assertSee('Lancer maintenant')
            ->assertSee('Kekeli lance votre automatisation…')
            ->assertSee('Préparer le contenu');

        $this->event($run, ['type' => 'lancement', 'ok' => false, 'resume' => "Le lancement s'est arrêté à l'étape « Envoyer l'e-mail » : refusé.", 'lignes' => [
            ['etape' => 'Préparer le contenu', 'statut' => 'ok', 'message' => 'Trois conseils prêts.'],
            ['etape' => "Envoyer l'e-mail", 'statut' => 'erreur', 'message' => 'Clé refusée : re_cle_inventee_pour_le_test_0123456789'],
        ]])->assertOk();

        $run->refresh();
        $this->assertSame(AgentRun::STATUS_COMPLETED, $run->status);
        $this->assertFalse(data_get($run->report, 'launch.ok'));
        $this->assertCount(2, data_get($run->report, 'launch.lines'));
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-launch-details', false)
            ->assertSee('Journal · 2 étapes')
            ->assertSee('data-launch-line="erreur"', false)
            ->assertSee('Envoyer l&#039;e-mail', false)
            ->assertDontSee('re_cle_inventee_pour_le_test_0123456789');
    }

    public function test_the_guided_choice_of_the_provider_and_the_free_trial_also_serve_the_automations(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->automation($user);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('data-model-form', false);
        $this->assertTrue(app(AgentTrial::class)->take($project)['permis']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(AgentRun $run, array $event): TestResponse
    {
        $body = json_encode(['tache' => $run->id, 'evenement' => $event], JSON_UNESCAPED_UNICODE);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/runner/evenements', server: $server, content: $body);
    }
}
