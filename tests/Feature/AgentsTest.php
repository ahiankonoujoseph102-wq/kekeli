<?php

namespace Tests\Feature;

use App\Agent\AgentTrial;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\Tools\CreateAgent;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Agents IA, comme Claude Code, Codex ou Cursor : Kekeli écrit le code de l'agent dans le projet du client ; le client
 * met la clé de son propre modèle dans les Variables secrètes et l'héberge où il veut. Le modèle et le Runner sont
 * simulés.
 */
class AgentsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const PLAN = "## Rôle\n- Assistante du Salon Belle Afi, en français\n\n## Ce qu'elle sait\n- Prestations et prix (FAQ)\n\n## Ce qu'elle peut faire\n- Proposer un rendez-vous\n\n## Où elle sera utilisée\n- Page de discussion et bulle sur le site\n\n## Modèle prévu\n- Votre propre clé (OpenAI ou Gemini), essai gratuit dans l'aperçu\n\nTechnologie : LangGraph (JavaScript)";

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendAgentRun::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function ask(User $user, string $text): Project
    {
        Volt::actingAs($user)->test('site-prompt')->set('description', $text)->call('build');

        return $user->projects()->latest('id')->firstOrFail();
    }

    private function agent(User $user, array $attributes = []): Project
    {
        $project = $user->projects()->make(['name' => 'Assistante du Salon Belle Afi']);
        $project->forceFill(['kind' => Project::KIND_WEBAPP, 'is_agent' => true, 'app_stack' => 'langgraph', 'app_template' => 'socle'] + $attributes)->save();

        return $project;
    }

    public function test_an_agent_request_shows_a_short_plan_with_a_build_button_before_any_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_agent', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non', 'nom' => 'Assistante du salon']);
        $chat = $this->ask($user, 'je veux une assistante qui répond aux clientes de mon salon sur les prix et qui propose des rendez-vous');

        $this->assertTrue($chat->fresh()->isChat(), 'aucune construction avant le plan accepté');
        Bus::assertNotDispatched(SendAgentRun::class);
        $this->assertSame(CreateAgent::OFFER, data_get($chat->agentRuns()->sole()->report, 'offer'));
        $this->assertSame('Assistante du salon', $chat->fresh()->name);
        $this->actingAs($user)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('<h2>Rôle</h2>', false)
            ->assertSee('<h2>Modèle prévu</h2>', false)
            ->assertSee('Technologie : LangGraph (JavaScript)')
            ->assertSee('data-build-plan', false)
            ->assertSee('Construire ce plan');
    }

    public function test_build_this_plan_turns_the_discussion_into_an_agent_in_langgraph_and_starts_the_work(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_agent', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        $chat = $this->ask($user, 'une assistante pour répondre aux clientes du Salon Belle Afi');
        $offer = app(CreateAgent::class)->lastOffer($chat);

        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', $offer->id);

        $project = $chat->fresh();
        $this->assertTrue($project->isAgent());
        $this->assertFalse($project->isApplication());
        $this->assertSame('langgraph', $project->app_stack);
        $this->assertNull($project->app_service);
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertSame('Construire ce plan', $run->message->content);
        $this->assertStringContainsString('Le client veut un AGENT IA', $run->instructions);
        $this->assertStringContainsString('construction NEUVE', $run->instructions);
        $this->assertStringContainsString('## Ce qu\'elle peut faire', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        // Le Runner reçoit l'agent, l'adresse signée de l'essai gratuit, le modèle de construction de la formule (comme
        // pour les applications), et jamais une clé de Kekeli.
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $request) => $request['application'] === ['pile' => 'langgraph', 'modele' => 'socle', 'installable' => false, 'service' => null]
            && str_contains((string) $request['essai_agent'], '/runner/projets/'.$project->id.'/essai-agent')
            && $request['fournisseurs'][0]['fournisseur'] === 'openai' && $request['fournisseurs'][0]['modele'] === 'gpt-6.1-terra'
            && ! str_contains($request->body(), 'GEMINI_API_KEY') && ! str_contains($request->body(), self::SECRET));
    }

    public function test_the_build_model_is_the_same_as_for_the_applications(): void
    {
        $pro = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $project = $this->agent($pro);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'ajoute la FAQ']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $request) => $request['fournisseurs'] === [$request['fournisseurs'][0]] && $request['fournisseurs'][0]['modele'] === 'gpt-6.1-sol');
    }

    public function test_the_technology_asked_by_the_client_is_always_respected(): void
    {
        $this->assertSame(['langgraph', null], CreateAgent::agentStackFor('auto', 'un assistant pour mes clients'));
        $this->assertSame(['crewai', null], CreateAgent::agentStackFor('crewai', 'un assistant'));
        $this->assertSame(['crewai', null], CreateAgent::agentStackFor('auto', 'je veux une équipe de plusieurs agents en Python'));
        $this->assertSame(['crewai', null], CreateAgent::agentStackFor('auto', 'fais le avec CrewAI'));
        $this->assertSame(['langgraph', null], CreateAgent::agentStackFor('auto', 'avec LangGraph'));
        // Une autre technologie imposée : respectée, à partir du point de départ le plus proche.
        $this->assertSame(['langgraph', 'Mastra'], CreateAgent::agentStackFor('mastra', 'avec Mastra'));
        $this->assertSame(['crewai', 'Python'], CreateAgent::agentStackFor('auto', 'je le veux en python'));
        $this->assertSame(['crewai', 'Llamaindex'], CreateAgent::agentStackFor('auto', 'avec llamaindex'));

        $user = User::factory()->create(['country' => 'TG']);
        $this->model->callsToolWith('creer_agent', ['plan' => self::PLAN, 'pile' => 'mastra', 'confirme' => 'non']);
        $chat = $this->ask($user, 'un assistant pour mes clientes, en Mastra');
        Volt::actingAs($user)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateAgent::class)->lastOffer($chat)->id);
        $project = $chat->fresh();
        $this->assertSame(['langgraph', 'Mastra'], [$project->app_stack, $project->app_service]);
        $this->assertStringContainsString('Technologie imposée par le client : Mastra. Respecte-la', $project->agentRuns()->whereNull('purpose')->sole()->instructions);
    }

    public function test_the_test_panel_shows_the_free_trial_then_its_end_then_the_clients_own_key(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->agent($user);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Tester l’agent')
            ->assertSee('data-agent-trial="free"', false)
            ->assertSee('Essai gratuit : 10 messages restants aujourd’hui')
            ->assertSee('Ajouter votre clé');

        app(Settings::class)->set('agent_trial_messages_per_day', 1);
        app(AgentTrial::class)->take($project);
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-agent-trial="over"', false)
            ->assertSee('L’essai gratuit du jour est terminé.')
            ->assertSee('Ajoutez votre clé dans les Variables secrètes pour continuer');

        $project->secrets()->create(['name' => 'MODELE_API_CLE', 'value' => 'cle-du-client-123456']);
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-agent-trial="own-key"', false)
            ->assertSee('Votre agent utilise votre propre clé (Variables secrètes).')
            ->assertDontSee('cle-du-client-123456');
    }

    private function trial(Project $project): TestResponse
    {
        $body = json_encode(['projet' => $project->id]);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach (RunnerSignature::headers(self::SECRET, $body) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', route('runner.agent-trial', $project), [], [], [], $server, $body);
    }

    public function test_the_runner_asks_the_site_for_each_trial_message_and_the_limit_per_day_and_per_client_is_kept(): void
    {
        app(Settings::class)->set('agent_trial_messages_per_day', 2);
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->agent($user);
        $other = $this->agent($user);

        $this->trial($project)->assertOk()->assertExactJson(['permis' => true, 'restants' => 1]);
        // La limite est par client : un autre agent du même client compte aussi.
        $this->trial($other)->assertOk()->assertExactJson(['permis' => true, 'restants' => 0]);
        $this->trial($project)->assertOk()->assertExactJson(['permis' => false, 'restants' => 0]);
        $this->assertSame(2, (int) ProviderUsage::query()->where('provider', 'gemini')->sum('requests'));

        // Le lendemain (minuit, heure de Lomé), l'essai revient.
        $this->travel(1)->days();
        $this->trial($project)->assertOk()->assertJson(['permis' => true]);

        // Sans signature : refusé. Un projet qui n'est pas un agent : jamais d'essai.
        $this->postJson(route('runner.agent-trial', $project), ['projet' => $project->id])->assertUnauthorized();
        $site = $user->projects()->create(['name' => 'Site']);
        $this->trial($site)->assertOk()->assertExactJson(['permis' => false, 'restants' => 0]);
    }

    public function test_the_free_trial_setting_is_in_the_administration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('data-setting="agent_trial_messages_per_day"', false);
        Volt::actingAs($admin)->test('admin.settings')->set('general.agent_trial_messages_per_day', 25)->call('save')->assertHasNoErrors();
        $this->assertSame(25, app(Settings::class)->int('agent_trial_messages_per_day'));
    }

    public function test_get_the_code_shows_the_bubble_to_copy_and_the_folded_actions_are_detailed(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $this->agent($user);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'construis']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->steps()->create(['kind' => 'fichier_modifie', 'target' => '/home/user/projet/agent/role.md']);
        $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'report' => ['done' => 'Votre agent est prêt.', 'note' => null, 'files' => [], 'files_count' => 0, 'tests' => [], 'problems' => []]])->save();

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Le code de votre agent')
            ->assertSee('data-agent-bubble', false)
            ->assertSee('/bulle.js', false)
            ->assertSee('agent/role.md')
            ->assertDontSee('Ce site a besoin d’un hébergeur avec base de données');
    }

    public function test_in_doubt_the_first_responder_asks_one_short_question_and_starts_nothing(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $this->model->answers('Voulez-vous un site qui présente votre salon, une application pour gérer les rendez-vous, ou un assistant qui répond à vos clientes ?');
        $chat = $this->ask($user, "j'ai un salon de coiffure à Lomé");

        $this->assertTrue($chat->fresh()->isChat());
        Bus::assertNotDispatched(SendAgentRun::class);
        $this->assertNotSame(CreateAgent::OFFER, data_get($chat->agentRuns()->sole()->report, 'offer'));
    }
}
