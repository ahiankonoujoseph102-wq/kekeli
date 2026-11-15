<?php

namespace Tests\Feature;

use App\Agent\AgentPlaces;
use App\Agent\Energy;
use App\Agent\RunnerClient;
use App\Billing\PlanPerks;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use App\Support\Connectors;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Une seule expérience pour tous : les formules payantes ajoutent seulement de la capacité et des avantages (report de
 * l'énergie au mois suivant, priorité, avant-première, assistance WhatsApp, plusieurs projets en même temps).
 */
class PlanPerksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unused_energy_of_a_paid_month_is_carried_over_to_the_next_one_with_a_ceiling(): void
    {
        $energy = app(Energy::class);
        // Pro payé depuis 40 jours, renouvelé : il reste 20 jours, le mois en cours a commencé il y a 10 jours.
        $user = $this->subscriber('pro', startedDaysAgo: 40, daysLeft: 20);
        $start = $energy->monthStart($user);

        // Mois précédent : 1 000 FCFA utilisés sur 4 000 → 3 000 FCFA reportés.
        $this->spend($user, 1000, $start->subDays(5));
        $this->assertEqualsWithDelta(3000, $energy->carriedOverFcfa($user), 0.5);
        $this->assertEqualsWithDelta(7000, $energy->limitFcfa($user), 0.5);

        // Ce mois-ci : 5 000 FCFA utilisés, plus que l'énergie habituelle, mais pas épuisée grâce au report.
        $this->spend($user, 5000, $start->addDay());
        $this->assertFalse($energy->isExhausted($user));
        $this->assertSame(29, $energy->percentLeft($user));

        // Au plus un mois d'énergie : rien utilisé le mois précédent → 4 000 FCFA, donc le double au plus.
        $other = $this->subscriber('pro', startedDaysAgo: 40, daysLeft: 20);
        $this->assertEqualsWithDelta(4000, $energy->carriedOverFcfa($other), 0.5);
        $this->assertEqualsWithDelta(8000, $energy->limitFcfa($other), 0.5);
    }

    public function test_no_carry_over_for_the_free_formula_nor_for_a_client_who_just_subscribed(): void
    {
        $energy = app(Energy::class);

        $free = User::factory()->create(['plan' => 'gratuite']);
        $this->assertSame(0.0, $energy->carriedOverFcfa($free));
        $this->assertEqualsWithDelta(100, $energy->limitFcfa($free), 0.01);

        // Abonné depuis 10 jours : le mois précédent n'était pas payé, rien n'est reporté.
        $new = $this->subscriber('pro', startedDaysAgo: 10, daysLeft: 20);
        $this->assertSame(0.0, $energy->carriedOverFcfa($new));

        // Le report se coupe dans l'administration.
        $old = $this->subscriber('expert', startedDaysAgo: 40, daysLeft: 20);
        $this->assertEqualsWithDelta(11000, $energy->carriedOverFcfa($old), 0.5);
        $plans = app(Settings::class)->get('plans');
        $plans['expert']['carry_over'] = false;
        app(Settings::class)->set('plans', $plans);
        $this->assertSame(0.0, $energy->carriedOverFcfa($old->fresh()));
    }

    public function test_the_carried_over_energy_is_shown_in_the_settings_of_the_formula(): void
    {
        $user = $this->subscriber('pro', startedDaysAgo: 40, daysLeft: 20);
        $this->spend($user, 2000, app(Energy::class)->monthStart($user)->subDays(3));

        Volt::actingAs($user)->test('settings.plan')
            ->assertSee('Énergie reportée du mois dernier : +50 %')
            ->assertSee('Le plus populaire', false)
            ->assertSee('Énergie non utilisée reportée au mois suivant');
    }

    public function test_when_kekeli_is_very_busy_agence_passes_first_then_expert_then_pro_then_gratuite(): void
    {
        app(Settings::class)->set('max_concurrent_runs', 1);
        $places = app(AgentPlaces::class);
        $this->runFor('expert', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);

        $free = $this->runFor('gratuite');
        $this->travel(1)->seconds();
        $pro = $this->runFor('pro');
        $this->travel(1)->seconds();
        $expert = $this->runFor('expert');
        $this->travel(1)->seconds();
        $agence = $this->runFor('agence');
        // Une formule payée mais finie compte comme gratuite.
        $expired = $this->runFor('agence', [], expired: true);

        $this->assertSame(0, $places->ahead($agence));
        $this->assertSame(1, $places->ahead($expert));
        $this->assertSame(2, $places->ahead($pro));
        $this->assertSame(3, $places->ahead($free));
        $this->assertSame(4, $places->ahead($expired));
        $this->assertSame(1, $places->position($agence));

        // Quand tous les fournisseurs sont occupés, la formule la plus haute essaie de nouveau la première.
        $perks = app(PlanPerks::class);
        $this->assertSame(2, $perks->retryMinutes($agence->project->user));
        $this->assertSame(3, $perks->retryMinutes($expert->project->user));
        $this->assertSame(4, $perks->retryMinutes($pro->project->user));
        $this->assertSame(5, $perks->retryMinutes($free->project->user));
    }

    public function test_several_projects_at_the_same_time_for_expert_and_agence_one_for_gratuite_and_pro(): void
    {
        $places = app(AgentPlaces::class);

        foreach (['gratuite' => true, 'pro' => true, 'expert' => false, 'agence' => false] as $plan => $waits) {
            $first = $this->runFor($plan, ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
            $other = $first->project->user->projects()->create(['name' => 'Deuxième projet']);
            $message = $other->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon site']);
            $second = $other->agentRuns()->create(['message_id' => $message->id]);

            $this->assertSame($waits, $places->waitsForOwnRun($second), $plan);
            $this->assertSame(! $waits, $places->canStart($second), $plan);
        }

        // Une question ne prend jamais de place : elle n'attend pas l'autre projet.
        $question = $this->runFor('gratuite', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $ask = $question->project->agentRuns()->create(['message_id' => $question->message_id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $this->assertFalse($places->waitsForOwnRun($ask));
    }

    public function test_the_waiting_request_says_that_kekeli_finishes_the_other_project_first(): void
    {
        $first = $this->runFor('pro', ['status' => AgentRun::STATUS_RUNNING, 'sent_at' => now()]);
        $user = $first->project->user;
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon site']);
        $project->agentRuns()->create(['message_id' => $message->id]);

        $this->actingAs($user)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Kekeli termine d’abord votre autre projet.', false);
    }

    public function test_early_access_opens_a_function_first_to_expert_and_agence(): void
    {
        $perks = app(PlanPerks::class);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $expert = User::factory()->create(['plan' => 'expert', 'plan_expires_at' => now()->addDays(10)]);
        $free = User::factory()->create(['plan' => 'gratuite']);

        // Rien en avant-première : tout est ouvert à tous.
        $this->assertSame([], $perks->reservedTools($free));
        $this->assertTrue($perks->canUseTool($free, 'creer_video'));

        app(Settings::class)->set('early_access_tools', ['creer_video', 'inconnu']);
        $this->assertSame(['creer_video'], $perks->reservedTools($pro));
        $this->assertFalse($perks->canUseTool($free, 'creer_video'));
        $this->assertTrue($perks->canUseTool($pro, 'creer_site'));
        $this->assertSame([], $perks->reservedTools($expert));

        // Le Runner ne propose pas l'outil réservé au modèle.
        $project = $pro->projects()->create(['name' => 'Discussion']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une vidéo de ma boutique']);
        $run = $project->agentRuns()->create(['message_id' => $message->id, 'purpose' => AgentRun::PURPOSE_QUESTION]);
        $sent = null;
        Http::fake(function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response(['ok' => true, 'decision' => 'repondre', 'reponse' => 'Bonjour']);
        });
        config(['kekeli.runner.url' => 'http://runner.test', 'kekeli.runner.secret' => 'secret-de-test']);
        app(RunnerClient::class)->respond($run, 'discussion');
        $this->assertSame(['creer_video'], data_get($sent, 'contexte.outils_reserves'));
    }

    public function test_the_admin_sets_the_advantages_and_the_functions_in_early_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('admin.settings')
            ->assertSee('Fonctions en avant-première')
            ->set('plans.pro.parallel_runs', 2)
            ->set('plans.pro.whatsapp_support', true)
            ->set('earlyAccessTools', ['creer_video'])
            ->call('save')
            ->assertHasNoErrors();

        $plans = app(Settings::class)->get('plans');
        $this->assertSame(2, $plans['pro']['parallel_runs']);
        $this->assertTrue($plans['pro']['whatsapp_support']);
        $this->assertTrue($plans['pro']['carry_over']);
        $this->assertSame(['creer_video'], app(Settings::class)->get('early_access_tools'));
    }

    public function test_the_whatsapp_help_is_only_for_agence(): void
    {
        $agence = User::factory()->create(['plan' => 'agence', 'plan_expires_at' => now()->addDays(10)]);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);

        $this->actingAs($agence)->get(route('settings.plan'))->assertOk()->assertSee('data-whatsapp-support', false)->assertSee('Assistance WhatsApp');
        $this->actingAs($agence)->get(route('projects.index'))->assertSee('data-whatsapp-support', false);
        $this->actingAs($pro)->get(route('settings.plan'))->assertOk()->assertDontSee('data-whatsapp-support', false);
        $this->actingAs($pro)->get(route('projects.index'))->assertDontSee('data-whatsapp-support', false)->assertSee('data-contact-whatsapp', false);
    }

    public function test_the_ratios_of_energy_are_read_from_the_settings_and_always_true(): void
    {
        $perks = app(PlanPerks::class);

        // 4 000 FCFA par mois contre 100 FCFA par jour (3 000 par mois) : 1,33 → « 1 » (la carte de Pro ne montre alors
        // pas de multiplicateur, App\Billing\PlanCards) ; avec l'ancienne Gratuite de 30 FCFA par jour : 4,4 → « 4 ».
        $this->assertEqualsWithDelta(1.33, $perks->ratio('pro', 'gratuite'), 0.01);
        $this->assertSame('1', $perks->ratioText('pro', 'gratuite'));
        $plans = app(Settings::class)->get('plans');
        $plans['gratuite']['energy_fcfa'] = 30;
        app(Settings::class)->set('plans', $plans);
        $this->assertSame('4', $perks->ratioText('pro', 'gratuite'));
        // 11 000 contre 4 000 : 2,75 → « près de 3 ».
        $this->assertSame('près de 3', $perks->ratioText('expert', 'pro'));
        $this->assertSame('7', $perks->ratioText('agence', 'pro'));
    }

    public function test_every_tool_stays_in_the_free_formula(): void
    {
        $free = User::factory()->create(['plan' => 'gratuite']);
        $perks = app(PlanPerks::class);

        foreach (array_keys(PlanPerks::EARLY_ACCESS_TOOLS) as $tool) {
            $this->assertTrue($perks->canUseTool($free, $tool), $tool);
        }
        // Mémoire et connecteurs : ouverts (deux connecteurs actifs à la fois), mise en ligne et GitHub sans condition de formule.
        $this->assertTrue((bool) $free->memory_enabled);
        $this->assertSame(2, app(Connectors::class)->maxActive($free));
    }

    private function subscriber(string $plan, int $startedDaysAgo, int $daysLeft): User
    {
        $user = User::factory()->create(['plan' => $plan, 'plan_expires_at' => now()->addDays($daysLeft)]);
        $price = (int) app(Settings::class)->get('plans')[$plan]['price_fcfa'];
        // Paiements successifs de 30 jours qui couvrent toute la période.
        $first = now()->toImmutable()->subDays($startedDaysAgo);
        for ($starts = now()->toImmutable()->addDays($daysLeft)->subDays(30); $starts->greaterThan($first->subDays(30)); $starts = $starts->subDays(30)) {
            $from = $starts->max($first);
            Payment::forceCreate(['user_id' => $user->id, 'plan' => $plan, 'amount_fcfa' => $price, 'country' => 'TG', 'screenshot_path' => 'paiements/x.png', 'status' => Payment::STATUS_VALIDATED, 'starts_at' => $from, 'expires_at' => $starts->addDays(30)]);
        }

        return $user;
    }

    private function spend(User $user, float $fcfa, \DateTimeInterface $at): void
    {
        $project = $user->projects()->create(['name' => 'Projet']);
        $run = $project->agentRuns()->create([]);
        $run->forceFill(['model_cost_usd' => $fcfa / app(Settings::class)->float('usd_to_fcfa'), 'created_at' => $at])->save();
    }

    private function runFor(string $plan, array $attributes = [], bool $expired = false): AgentRun
    {
        $user = User::factory()->create();
        $user->forceFill(['plan' => $plan, 'plan_expires_at' => $plan === 'gratuite' ? null : ($expired ? now()->subDay() : now()->addDays(30))])->save();
        $project = $user->projects()->create(['name' => 'Projet']);
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Crée mon application']);
        $run = $project->agentRuns()->create(['message_id' => $message->id]);
        $run->forceFill($attributes)->save();

        return $run;
    }
}
