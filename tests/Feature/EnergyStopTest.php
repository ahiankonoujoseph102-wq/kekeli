<?php

namespace Tests\Feature;

use App\Agent\ImageQuota;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EnergyStopTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kekeli.runner.secret' => self::SECRET,
            'kekeli.runner.url' => 'http://runner.test',
            'kekeli.providers_order' => 'gemini',
            'kekeli.settings.usd_to_fcfa' => 600,
            'kekeli.settings.free_daily_cap_fcfa' => 0,
            'kekeli.settings.plans' => [
                'gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'energy_fcfa' => 30, 'window_hours' => 0],
                'pro' => ['name' => 'Pro', 'price_fcfa' => 7500, 'energy_fcfa' => 130, 'window_hours' => 0],
            ],
        ]);
    }

    public function test_the_runner_is_told_to_stop_as_soon_as_the_energy_is_used_up(): void
    {
        [$user, $project, $run] = $this->runningRun();

        // Une petite requête : l'énergie n'est pas épuisée, le travail continue.
        $this->event($run, ['type' => 'requete', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 1000, 'tokens_sortie' => 100])
            ->assertOk()
            ->assertJsonMissingPath('arreter');

        // Une grosse requête : 10 millions de tokens envoyés à 0,30 $ le million = 3 $, soit 1 800 FCFA > 30 FCFA.
        $this->event($run, ['type' => 'requete', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 10_000_000])
            ->assertOk()
            ->assertJson(['arreter' => true, 'raison' => 'energie']);

        // La demande reste arrêtée pour chaque événement suivant, jusqu'à la fin.
        $this->event($run, ['type' => 'etape', 'genre' => 'sauvegarde', 'cible' => '12 Ko'])->assertJson(['arreter' => true]);
        $this->assertNotNull($run->fresh()->stop_requested_at);

        $this->event($run, ['type' => 'arretee', 'raison' => 'energie'])->assertOk();
        $run->refresh();
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->status);
        $this->assertSame('energie', $run->stop_reason);
        $this->assertNotNull($run->finished_at);
        $this->assertGreaterThan(0, $run->computer_seconds);
        $this->assertFalse($run->isActive());
    }

    public function test_the_user_sees_the_stop_then_continues_after_the_recharge(): void
    {
        Queue::fake();
        [$user, $project, $run] = $this->runningRun();
        $run->forceFill(['status' => AgentRun::STATUS_STOPPED, 'stop_reason' => 'energie', 'model_cost_usd' => 1, 'computer_seconds' => 120, 'finished_at' => now()])->save();

        // Énergie épuisée : message clair, heure de recharge (Lomé) et bouton « Voir les formules », pas de « Continuer ».
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Votre énergie est épuisée : Kekeli a arrêté le travail.')
            ->assertSee('Votre projet est sauvegardé tel quel, sans aucune perte.')
            ->assertSee('(heure de Lomé)')
            ->assertSee('Voir les formules')
            ->assertDontSee('data-continue', false);

        $component = Volt::actingAs($user)->test('projects.show', ['project' => $project])->call('continueRun', $run->id);
        Queue::assertNothingPushed();
        $this->assertSame(AgentRun::STATUS_STOPPED, $run->fresh()->status);

        // Après le paiement d'une formule (ou la recharge), le bouton « Continuer » reprend la même demande.
        $user->update(['plan' => 'pro']);
        $run->forceFill(['model_cost_usd' => 0.01])->save();
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('Votre énergie est rechargée')
            ->assertSee('data-continue', false);

        $component->call('continueRun', $run->id);
        $run->refresh();
        $this->assertSame(AgentRun::STATUS_QUEUED, $run->status);
        $this->assertNull($run->stop_requested_at);
        $this->assertNull($run->started_at);
        Queue::assertPushed(SendAgentRun::class, fn (SendAgentRun $job) => $job->run->is($run) && $job->resume);
    }

    public function test_a_continued_request_is_sent_as_a_resumption_and_keeps_its_computer_time(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        [$user, $project, $run] = $this->runningRun();
        $run->update(['status' => AgentRun::STATUS_QUEUED, 'computer_seconds' => 100]);

        (new SendAgentRun($run, true))->handle(app(RunnerClient::class));
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches' && $request['reprise'] === true && $request['tache'] === $run->id);

        // Le temps de l'ordinateur d'avant l'arrêt est gardé et le nouveau s'y ajoute.
        $run->update(['status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(30)]);
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Le site est prêt.'])->assertOk();
        $this->assertEqualsWithDelta(130, $run->fresh()->computer_seconds, 2);
    }

    public function test_the_number_of_images_created_per_day_is_limited_for_all_users(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        config(['kekeli.settings.images_max_per_day' => 10]);
        [$user, $project, $run] = $this->runningRun();
        $run->forceFill(['images_created' => 7])->save();

        $this->assertSame(3, app(ImageQuota::class)->remainingToday());

        $other = User::factory()->create()->projects()->create(['name' => 'Autre']);
        $message = $other->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée une affiche']);
        $other->agentRuns()->create(['message_id' => $message->id])->forceFill(['images_created' => 5])->save();
        $this->assertSame(0, app(ImageQuota::class)->remainingToday());

        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Encore']);
        $next = $project->agentRuns()->create(['message_id' => $message->id]);
        (new SendAgentRun($next))->handle(app(RunnerClient::class));
        Http::assertSent(fn (Request $request) => $request['images_restantes'] === 0);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertSee("Nombre maximum d'images créées par jour (tous les utilisateurs)");
    }

    /**
     * @return array{0: User, 1: Project, 2: AgentRun}
     */
    private function runningRun(): array
    {
        $user = User::factory()->create(['plan' => 'gratuite']);
        $project = $user->projects()->create(['name' => 'Restaurant']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée le site du restaurant']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'status' => AgentRun::STATUS_RUNNING, 'started_at' => now()->subSeconds(60)]);

        return [$user, $project, $run];
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
