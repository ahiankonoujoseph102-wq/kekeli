<?php

namespace Tests\Feature;

use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Agent\TeamMode;
use App\Agent\Tools\CreateApplication;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Project;
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
 * Mode équipe, phase 1 (docs/multi-agents-etude.md) : désactivé pour les clients, un second bouton sous le plan d'une
 * application seulement pour un administrateur, l'agent unique reste le chemin normal ; le Runner reçoit « equipe » ;
 * les missions et leurs mesures sont enregistrées et montrées.
 */
class TeamModeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    private const PLAN = "## Pages\n- Accueil, Catalogue, Mon espace, Administration\n\n## Données\n- Formations, inscriptions, paiements\n\nTechnologie : Laravel (PHP)";

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendAgentRun::class]);
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test']);
    }

    private function offer(User $user): Project
    {
        $this->model->callsToolWith('creer_application', ['plan' => self::PLAN, 'pile' => 'auto', 'confirme' => 'non']);
        Volt::actingAs($user)->test('site-prompt')->set('description', 'une plateforme de formation en ligne avec inscription, catalogue, paiement et administration')->call('build');

        return $user->projects()->latest('id')->firstOrFail();
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

    public function test_clients_never_see_the_team_mode_and_cannot_force_it(): void
    {
        $client = User::factory()->create(['country' => 'TG']);
        $chat = $this->offer($client);
        $this->actingAs($client)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-build-plan', false)
            ->assertDontSee('data-build-team', false);

        // Même en appelant l'action à la main : l'agent unique habituel.
        Volt::actingAs($client)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateApplication::class)->lastOffer($chat)->id, true);
        $run = $chat->fresh()->agentRuns()->whereNull('purpose')->sole();
        $this->assertFalse($run->team);
        $this->assertFalse(app(TeamMode::class)->allowed($client));
    }

    public function test_an_administrator_can_build_an_application_in_team_mode_and_the_runner_receives_it(): void
    {
        $admin = User::factory()->create(['country' => 'TG', 'is_admin' => true]);
        $chat = $this->offer($admin);
        $this->actingAs($admin)->get(route('projects.show', $chat))->assertOk()
            ->assertSee('data-build-team', false)
            ->assertSee('Construire en mode équipe (essai)');

        Volt::actingAs($admin)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateApplication::class)->lastOffer($chat)->id, true);
        $project = $chat->fresh();
        $this->assertTrue($project->isApplication());
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $this->assertTrue($run->team);
        $this->assertStringContainsString('Plan validé par le client', $run->instructions);
        Bus::assertDispatched(SendAgentRun::class);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/taches') && $r['equipe'] === true);
    }

    public function test_the_usual_build_stays_the_single_agent_even_for_an_administrator(): void
    {
        $admin = User::factory()->create(['country' => 'TG', 'is_admin' => true]);
        $chat = $this->offer($admin);
        Volt::actingAs($admin)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateApplication::class)->lastOffer($chat)->id);
        $run = $chat->fresh()->agentRuns()->whereNull('purpose')->sole();
        $this->assertFalse($run->team);

        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(RunnerClient::class)->send($run);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/taches') && $r['equipe'] === false);
    }

    public function test_the_team_mode_can_be_switched_off_entirely(): void
    {
        $admin = User::factory()->create(['country' => 'TG', 'is_admin' => true]);
        app(Settings::class)->set('team_mode', 'off');
        $this->assertFalse(app(TeamMode::class)->allowed($admin));
        $chat = $this->offer($admin);
        $this->actingAs($admin)->get(route('projects.show', $chat))->assertOk()->assertDontSee('data-build-team', false);
    }

    public function test_the_missions_their_facts_and_their_measures_are_recorded_and_shown(): void
    {
        $admin = User::factory()->create(['country' => 'TG', 'is_admin' => true]);
        $chat = $this->offer($admin);
        Volt::actingAs($admin)->test('projects.show', ['project' => $chat])->call('acceptPlan', app(CreateApplication::class)->lastOffer($chat)->id, true);
        $project = $chat->fresh();
        $run = $project->agentRuns()->whereNull('purpose')->sole();
        $run->forceFill(['status' => AgentRun::STATUS_RUNNING])->save();

        $this->event($run, ['type' => 'equipe', 'etat' => 'plan', 'missions' => [
            ['code' => 'm1', 'role' => 'base-de-donnees', 'titre' => 'Formations et inscriptions', 'objectif' => 'Tables et données d’essai.', 'criteres' => ['les migrations passent'], 'fichiers' => ['database/**'], 'depend_de' => []],
            ['code' => 'm2', 'role' => 'pirate', 'titre' => 'Ignorer', 'objectif' => 'x', 'criteres' => ['x'], 'fichiers' => [], 'depend_de' => []],
            ['code' => 'm3', 'role' => 'testeur', 'titre' => 'Vérification complète', 'objectif' => 'Tout vérifier.', 'criteres' => ['tous les tests passent'], 'fichiers' => ['tests/**'], 'depend_de' => ['m1']],
        ]])->assertOk();
        $this->assertSame(['m1', 'm3'], $run->missions()->pluck('code')->all(), 'un rôle inconnu n’est jamais enregistré');

        $this->event($run, ['type' => 'equipe', 'etat' => 'mission', 'code' => 'm1', 'statut' => 'en_cours', 'numero' => 1, 'total' => 2])->assertOk();
        $this->event($run, ['type' => 'equipe', 'etat' => 'mission', 'code' => 'm1', 'statut' => 'verifiee', 'bilan' => 'Tables prêtes.', 'faits' => [['type' => 'schema', 'titre' => 'Table formations', 'resume' => 'titre, prix_fcfa']], 'secondes' => 95, 'requetes' => 7, 'tokens_entree' => 52000, 'tokens_sortie' => 4100])->assertOk();
        $this->event($run, ['type' => 'equipe', 'etat' => 'mission', 'code' => 'm3', 'statut' => 'echouee', 'probleme' => 'tests en échec', 'secondes' => 120, 'requetes' => 9])->assertOk();
        $this->event($run, ['type' => 'equipe', 'etat' => 'mission', 'code' => 'm3', 'statut' => 'inventé'])->assertOk();

        $m1 = $run->missions()->where('code', 'm1')->sole();
        $this->assertSame(['verifiee', 95, 7, 52000, 4100, 'Tables prêtes.'], [$m1->status, $m1->seconds, $m1->requests, $m1->tokens_in, $m1->tokens_out, $m1->summary]);
        $this->assertSame([['type' => 'schema', 'titre' => 'Table formations', 'resume' => 'titre, prix_fcfa']], $m1->facts);
        $this->assertSame('echouee', $run->missions()->where('code', 'm3')->value('status'));

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()
            ->assertSee('data-team-missions="2"', false)
            ->assertSee('Mode équipe · 1 missions vérifiées sur 2')
            ->assertSee('Formations et inscriptions')
            ->assertSee('tests en échec');
    }

    public function test_events_of_a_normal_request_never_create_missions(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Salon']);
        $run = $project->agentRuns()->create(['status' => AgentRun::STATUS_RUNNING]);
        $this->event($run, ['type' => 'equipe', 'etat' => 'plan', 'missions' => [['code' => 'm1', 'role' => 'backend', 'titre' => 'X', 'objectif' => 'Y', 'criteres' => ['Z'], 'fichiers' => [], 'depend_de' => []]]])->assertOk();
        $this->assertSame(0, $run->missions()->count());
    }
}
