<?php

namespace Tests\Feature;

use App\Agent\Costs;
use App\Agent\RunnerClient;
use App\Agent\RunnerSignature;
use App\Jobs\ErasePausedComputer;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConsumptionTest extends TestCase
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
            'kekeli.settings.plans' => [
                'gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'energy_fcfa' => 30, 'window_hours' => 0],
                'pro' => ['name' => 'Pro', 'price_fcfa' => 5000, 'energy_fcfa' => 300, 'window_hours' => 5],
            ],
        ]);
    }

    // Coûts

    public function test_costs_follow_the_price_grid(): void
    {
        $costs = app(Costs::class);

        // Gemini 3.5 Flash-Lite : 0,30 $ en entrée, 2,50 $ en sortie, 0,03 $ en cache, par million.
        // Un million de chaque : entrée 0,30 + sortie 2,50 + réflexion 2,50 (payée comme la sortie) + cache 0,03.
        $this->assertEqualsWithDelta(5.33, $costs->modelCost('gemini', 'gemini-3.5-flash-lite', 1_000_000, 1_000_000, 1_000_000, 1_000_000), 0.000001);
        $this->assertSame(0.0, $costs->modelCost('mistral', 'modele-inconnu', 1_000_000, 0, 0, 0));

        // 2 processeurs à 0,000014 $ et 0,5 Go à 0,0000045 $ par seconde.
        $this->assertEqualsWithDelta(2 * 0.000014 + 0.5 * 0.0000045, $costs->computerCostPerSecond(), 1e-12);
        $this->assertEqualsWithDelta(590.0, $costs->toFcfa(1.0), 0.001);
    }

    public function test_each_request_adds_its_tokens_and_cost_to_the_run_and_the_computer_time_is_counted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'UTC'));
        $run = $this->agentRun();

        $this->event($run, ['type' => 'demarrage'])->assertOk();
        $event = ['type' => 'requete', 'fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'tokens_entree' => 10000, 'tokens_sortie' => 1000, 'tokens_reflexion' => 200, 'tokens_cache' => 5000];
        $this->event($run, $event)->assertOk();
        $this->event($run, $event)->assertOk();

        $this->travel(90)->seconds();
        $this->event($run, ['type' => 'terminee', 'reponse' => 'Fait.'])->assertOk();

        $run->refresh();
        $this->assertSame(2, $run->requests);
        $this->assertSame(20000, $run->input_tokens);
        $this->assertSame(2000, $run->output_tokens);
        $this->assertSame(400, $run->reasoning_tokens);
        $this->assertSame(10000, $run->cached_tokens);
        $this->assertEqualsWithDelta(2 * (10000 * 0.30 + 1200 * 2.50 + 5000 * 0.03) / 1_000_000, $run->model_cost_usd, 0.000001);
        $this->assertSame(90, $run->computer_seconds);
        $this->assertEqualsWithDelta(90 * (2 * 0.000014 + 0.5 * 0.0000045), $run->computer_cost_usd, 0.000001);
    }

    // Énergie et messages de limite

    public function test_a_free_user_whose_energy_is_used_up_sees_the_free_message_and_nothing_is_sent(): void
    {
        Http::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00', 'UTC'));
        [$user, $project] = $this->project();

        $page = $this->actingAs($user)->get(route('projects.show', $project));
        $page->assertSee('data-energy="100"', false)->assertDontSee('data-energy-exhausted', false);

        // 0,06 $ = 35,4 FCFA : plus que les 30 FCFA d'énergie du jour.
        $this->spend($project, 0.06);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-energy-exhausted="free"', false)
            ->assertSee('☀️ Votre énergie du jour est épuisée. Kekeli se recharge et sera de nouveau prêt à 00:00. Vous ne voulez pas attendre ? Passez à une formule payante et continuez tout de suite.')
            ->assertSee('Voir les formules')
            ->assertDontSee('wire:submit="send"', false);

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Encore une page')
            ->call('send');

        $this->assertSame(1, AgentRun::count());
        Http::assertNothingSent();

        // Le lendemain à minuit (heure de Lomé), l'énergie est de nouveau pleine.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 00:01', 'UTC'));
        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertSee('data-energy="100"', false)
            ->assertDontSee('data-energy-exhausted', false);
    }

    public function test_a_paid_user_has_a_period_that_starts_with_the_first_request_and_sees_the_paid_message(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00', 'UTC'));
        [$user, $project] = $this->project();
        $user->forceFill(['plan' => 'pro'])->save();

        Volt::actingAs($user)->test('projects.show', ['project' => $project])
            ->set('content', 'Crée une page')
            ->call('send');

        $this->assertTrue($user->fresh()->energy_window_started_at->equalTo(now()));
        AgentRun::query()->update(['status' => AgentRun::STATUS_COMPLETED, 'finished_at' => now()]);

        // 0,60 $ = 354 FCFA : plus que les 300 FCFA de la période.
        $this->spend($project, 0.60);

        $this->actingAs($user->fresh())->get(route('projects.show', $project))
            ->assertSee('data-energy-exhausted="paid"', false)
            ->assertSee('☀️ Vous avez utilisé toute l&#039;énergie de votre formule pour le moment. Elle se recharge à 14:00. Votre projet est sauvegardé : vous reprendrez exactement où vous vous êtes arrêté. Besoin de plus ? Passez à la formule supérieure.', false);

        // Cinq heures après le début de la période, l'énergie est rechargée.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 14:01', 'UTC'));
        $this->actingAs($user->fresh())->get(route('projects.show', $project))->assertSee('data-energy="100"', false);
    }

    public function test_the_formulas_page_shows_only_the_free_formula_until_the_prices_are_approved(): void
    {
        [$user] = $this->project();

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee('Votre formule : Gratuite')
            ->assertSee('data-plan="gratuite"', false)
            ->assertDontSee('data-plan="pro"', false)
            ->assertSee('Les formules payantes sont en préparation');

        app(Settings::class)->set('plans_published', true);

        $this->actingAs($user)->get(route('plans'))
            ->assertSee('data-plan="pro"', false)
            ->assertSee('5 000 FCFA par mois')
            ->assertDontSee('Les formules payantes sont en préparation');
    }

    // Administration

    public function test_only_the_administrator_sees_the_consumption_and_changes_a_formula(): void
    {
        [$user, $project] = $this->project();
        $this->spend($project, 0.01, ['requests' => 5, 'input_tokens' => 20000, 'output_tokens' => 2000]);
        $admin = $this->admin();

        $this->actingAs($user)->get(route('admin.consumption'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.consumption'))
            ->assertOk()
            ->assertSee('data-comparison', false)
            ->assertSee('Claude Sonnet 5.5')
            ->assertSee('Gemini 3.5 Flash-Lite')
            ->assertSee($user->email);

        Volt::actingAs($admin)->test('admin.consumption')->call('setPlan', $user->id, 'pro');
        $this->assertSame('pro', $user->fresh()->plan);

        Volt::actingAs($admin)->test('admin.consumption')->call('setPlan', $user->id, 'inconnue')->assertStatus(422);
    }

    public function test_the_administrator_changes_the_settings_and_can_restore_the_defaults(): void
    {
        $admin = $this->admin();
        $settings = app(Settings::class);

        $component = Volt::actingAs($admin)->test('admin.settings')
            ->set('general.pause_minutes', 10)
            ->set('general.erase_paused_after_days', 3)
            ->set('prices.0.input', 0.5)
            ->set('newPlan', 'Essentiel')
            ->call('addPlan')
            ->set('plans.essentiel.price_fcfa', 2000)
            ->set('plans.essentiel.energy_fcfa', 100)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(10, $settings->int('pause_minutes'));
        $this->assertSame(3, $settings->int('erase_paused_after_days'));
        $this->assertSame(0.5, $settings->get('model_prices')['gemini/gemini-3.5-flash-lite']['input']);
        $this->assertSame(2000, $settings->get('plans')['essentiel']['price_fcfa']);
        $this->assertFalse($settings->get('plans_published'));

        $component->set('general.pause_minutes', 2)->call('save')->assertHasErrors('general.pause_minutes');

        $component->call('restoreDefaults');
        $this->assertSame(30, $settings->int('pause_minutes'));
        $this->assertSame(0.30, $settings->get('model_prices')['gemini/gemini-3.5-flash-lite']['input']);
    }

    public function test_the_runner_receives_the_minutes_before_the_pause(): void
    {
        Http::fake(['runner.test/*' => Http::response(['accepte' => true], 202)]);
        app(Settings::class)->set('pause_minutes', 12);
        $run = $this->agentRun();

        app(RunnerClient::class)->send($run);
        app(RunnerClient::class)->wakePreview($run->project);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/taches' && $request['duree_vie_minutes'] === 12);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/apercus' && $request['duree_vie_minutes'] === 12);

        $this->actingAs($run->project->user)->get(route('projects.show', $run->project))->assertDontSee('30 minutes');
    }

    // Effacement automatique des ordinateurs en pause

    public function test_computers_paused_for_too_long_are_erased_only_when_the_backup_is_up_to_date(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:17', 'UTC'));

        $old = $this->pausedProject(daysAgo: 8, backupAfterLastRun: true);
        $recent = $this->pausedProject(daysAgo: 3, backupAfterLastRun: true);
        $withoutBackup = $this->pausedProject(daysAgo: 10, backupAfterLastRun: false);
        $alreadyErased = $this->pausedProject(daysAgo: 9, backupAfterLastRun: true);
        $alreadyErased->forceFill(['computer_erased_at' => now()->subDay()])->save();

        $this->artisan('kekeli:effacer-ordinateurs-en-pause')
            ->expectsOutputToContain('1 ordinateur(s) en pause depuis plus de 7 jours')
            ->assertSuccessful();

        Queue::assertPushed(ErasePausedComputer::class, 1);
        Queue::assertPushed(ErasePausedComputer::class, fn (ErasePausedComputer $job) => $job->project->is($old));

        // Le délai se règle dans l'administration.
        app(Settings::class)->set('erase_paused_after_days', 2);
        $this->artisan('kekeli:effacer-ordinateurs-en-pause')->assertSuccessful();
        Queue::assertPushed(ErasePausedComputer::class, fn (ErasePausedComputer $job) => $job->project->is($recent));
        Queue::assertNotPushed(ErasePausedComputer::class, fn (ErasePausedComputer $job) => $job->project->is($withoutBackup) || $job->project->is($alreadyErased));
    }

    public function test_erasing_a_paused_computer_keeps_the_project_and_its_backup(): void
    {
        Http::fake(['runner.test/*' => Http::response(['effaces' => 1])]);
        $project = $this->pausedProject(daysAgo: 8, backupAfterLastRun: true);
        $backupAt = $project->backup_at;

        (new ErasePausedComputer($project))->handle(app(RunnerClient::class));

        Http::assertSent(fn (Request $request) => $request->url() === 'http://runner.test/projets/effacer' && $request['projet'] === $project->id);
        $project->refresh();
        $this->assertNotNull($project->computer_erased_at);
        $this->assertTrue($project->backup_at->equalTo($backupAt));
        $this->assertSame(1, $project->messages()->count());

        $this->actingAs($project->user)->get(route('projects.show', $project))
            ->assertSee('data-computer-erased', false)
            ->assertSee('Votre projet est bien sauvegardé')
            ->assertSee('Rallumer l&#039;aperçu', false);
    }

    public function test_a_computer_in_use_is_never_erased(): void
    {
        Http::fake();
        $project = $this->pausedProject(daysAgo: 8, backupAfterLastRun: true);
        $project->forceFill(['preview_expires_at' => now()->addMinutes(20)])->save();

        (new ErasePausedComputer($project))->handle(app(RunnerClient::class));

        Http::assertNothingSent();
        $this->assertNull($project->fresh()->computer_erased_at);
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
     * A finished request that cost the given amount of dollars.
     *
     * @param  array<string, mixed>  $extra
     */
    private function spend(Project $project, float $usd, array $extra = []): AgentRun
    {
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une demande']);

        return tap($project->agentRuns()->create(['message_id' => $message->id]), fn (AgentRun $run) => $run->forceFill([
            'status' => AgentRun::STATUS_COMPLETED,
            'model_cost_usd' => $usd,
            'finished_at' => now(),
            ...$extra,
        ])->save());
    }

    private function pausedProject(int $daysAgo, bool $backupAfterLastRun): Project
    {
        [, $project] = $this->project();
        $run = $this->spend($project, 0.001);
        $run->forceFill(['finished_at' => now()->subDays($daysAgo)->subMinutes(30)])->save();
        $project->forceFill([
            'preview_url' => 'https://4100-abc.e2b.app',
            'preview_expires_at' => now()->subDays($daysAgo),
            'backup_at' => $backupAfterLastRun ? now()->subDays($daysAgo)->subMinutes(29) : now()->subDays($daysAgo)->subHours(2),
        ])->save();

        return $project->fresh();
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
