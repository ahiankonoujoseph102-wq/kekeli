<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Billing\Subscriptions;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Formulas of the 12th of November 2026 (docs/plan.md, part 25): Gratuite, Pro, Expert, Agence.
 */
class NewPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'UTC'));
    }

    public function test_a_paid_formula_gives_its_energy_for_each_month_and_the_free_one_for_each_day(): void
    {
        $energy = app(Energy::class);
        $pro = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(30)]);
        $project = $pro->projects()->create(['name' => 'Boutique']);

        // 2 000 FCFA dépensés en deux jours : bien plus que l'énergie d'un jour, la moitié du mois (4 000 FCFA).
        $this->spend($project, 1000);
        $this->travel(1)->days();
        $this->spend($project, 1000);

        $this->assertSame('month', $energy->plan($pro)['period']);
        $this->assertSame(50, $energy->percentLeft($pro));
        $this->assertFalse($energy->isExhausted($pro));
        $this->assertSame('12/12/2026 à 10:00', $energy->rechargeText($pro));

        $this->spend($project, 2100);
        $this->assertTrue($energy->isExhausted($pro->fresh()));

        // Le mois suivant (renouvellement payé d'avance), l'énergie est de nouveau pleine.
        $pro->forceFill(['plan_expires_at' => CarbonImmutable::parse('2027-01-11 10:00', 'UTC')])->save();
        $this->travelTo(CarbonImmutable::parse('2026-12-12 10:01', 'UTC'));
        $this->assertSame(100, $energy->percentLeft($pro->fresh()));

        // La formule gratuite garde son énergie du jour (30 FCFA), rechargée à minuit, heure de Lomé.
        $free = User::factory()->create();
        $this->assertSame(['day', 30.0], [$energy->plan($free)['period'], $energy->plan($free)['energy_fcfa']]);
        $this->assertSame('00:00', $energy->rechargeText($free));
    }

    public function test_the_subscribers_of_the_older_formulas_move_to_the_closest_one_without_losing_anything(): void
    {
        $decouverte = User::factory()->create(['plan' => 'decouverte', 'plan_expires_at' => now()->addDays(10)]);
        $entreprise = User::factory()->create(['plan' => 'entreprise', 'plan_expires_at' => now()->addDays(10)]);
        $ended = User::factory()->create(['plan' => 'decouverte', 'plan_expires_at' => now()->subDay()]);
        $waiting = User::factory()->create()->payments()->create(['plan' => 'decouverte', 'amount_fcfa' => 2500, 'country' => 'TG', 'screenshot_path' => 'paiements/x.png']);

        // Avant la migration, une ancienne formule compte déjà comme celle qui la remplace.
        $this->assertSame('expert', app(Energy::class)->plan($entreprise)['key']);

        $moved = app(Subscriptions::class)->moveToNewPlans(['decouverte' => 2500, 'entreprise' => 25000]);

        $this->assertSame(2, $moved);
        // Découverte (2 500 FCFA) → Pro, mêmes jours restants : rien de perdu, plus d'énergie.
        $this->assertSame('pro', $decouverte->fresh()->plan);
        $this->assertEqualsWithDelta(10, now()->diffInDays($decouverte->fresh()->plan_expires_at), 0.01);
        // Entreprise (25 000 FCFA) → Expert (20 000 FCFA) : 10 jours restants deviennent 12,5 jours.
        $this->assertSame('expert', $entreprise->fresh()->plan);
        $this->assertEqualsWithDelta(12.5, now()->diffInDays($entreprise->fresh()->plan_expires_at), 0.01);
        // Une formule déjà finie revient à la gratuite.
        $this->assertSame(['gratuite', null], [$ended->fresh()->plan, $ended->fresh()->plan_expires_at]);
        // Un paiement en attente passe à Pro ; validé, il donne des jours en proportion du montant payé (10 jours).
        $this->assertSame('pro', $waiting->fresh()->plan);
        app(Subscriptions::class)->validate($waiting->fresh(), User::factory()->create(['is_admin' => true]));
        $this->assertEqualsWithDelta(10, now()->diffInDays($waiting->user->fresh()->plan_expires_at), 0.01);
    }

    public function test_the_migration_replaces_the_saved_formulas_corrects_the_cache_price_and_lowers_the_free_ceiling(): void
    {
        $settings = app(Settings::class);
        $settings->set('plans', ['gratuite' => ['name' => 'Gratuite', 'price_fcfa' => 0, 'energy_fcfa' => 30, 'window_hours' => 0], 'entreprise' => ['name' => 'Entreprise', 'price_fcfa' => 25000, 'energy_fcfa' => 450, 'window_hours' => 0]]);
        $settings->set('free_daily_cap_fcfa', 1500);
        $prices = config('kekeli.settings.model_prices');
        $prices['anthropic/claude-sonnet-5-5']['cache'] = 0.20;
        $settings->set('model_prices', $prices);
        $user = User::factory()->create(['plan' => 'entreprise', 'plan_expires_at' => now()->addDays(4)]);

        (require database_path('migrations/2026_11_12_000001_move_to_new_plans.php'))->up();
        Cache::forget('kekeli.settings');

        $this->assertSame(['gratuite', 'pro', 'expert', 'agence'], array_keys($settings->get('plans')));
        $this->assertSame(1000.0, $settings->float('free_daily_cap_fcfa'));
        $this->assertSame(0.10, (float) $settings->get('model_prices')['anthropic/claude-sonnet-5-5']['cache']);
        $this->assertSame('expert', $user->fresh()->plan);
        $this->assertEqualsWithDelta(5, now()->diffInDays($user->fresh()->plan_expires_at), 0.01);
        $this->assertFalse(Setting::query()->whereKey('plans')->exists());
    }

    public function test_changing_formula_keeps_the_days_already_paid_at_their_value(): void
    {
        $user = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(8)]);
        $payment = $user->payments()->create(['plan' => 'expert', 'amount_fcfa' => 20000, 'country' => 'TG', 'screenshot_path' => 'paiements/y.png']);

        app(Subscriptions::class)->validate($payment, User::factory()->create(['is_admin' => true]));

        // 8 jours de Pro (7 500 FCFA) valent 3 jours d'Expert (20 000 FCFA), ajoutés aux 30 jours payés.
        $this->assertSame('expert', $user->fresh()->plan);
        $this->assertEqualsWithDelta(33, now()->diffInDays($user->fresh()->plan_expires_at), 0.01);
    }

    public function test_the_formulas_page_says_what_each_formula_allows_in_fcfa_or_in_dollars(): void
    {
        $togo = User::factory()->create(['country' => 'TG']);
        $page = $this->actingAs($togo)->get(route('plans'))->assertOk();
        $page->assertSeeInOrder(['Gratuite', '0 FCFA', 'Environ 1 site vitrine tous les 2 jours',
            'Pro', '7 500 FCFA', 'par mois', 'Environ 14 sites vitrines par mois', 'ou 7 sites avec base de données', 'Choisir Pro',
            'Expert', '20 000 FCFA', 'Environ 40 sites vitrines par mois', 'ou 20 sites avec base de données', 'Choisir Expert',
            'Agence', '50 000 FCFA', 'Environ 100 sites vitrines par mois', 'ou 50 sites avec base de données', 'Choisir Agence'])
            ->assertSee('Votre formule actuelle')
            ->assertDontSee('Découverte')
            ->assertDontSee('Entreprise')
            ->assertDontSeeText(' $');

        // Hors de la zone FCFA : prix en dollars, payés en FCFA par Mobile Money.
        $ghana = User::factory()->create(['country' => 'GH']);
        $this->actingAs($ghana)->get(route('plans'))
            ->assertSeeInOrder(['13 $', 'Payé 7 500 FCFA par Mobile Money', '34 $', '85 $']);
        Volt::actingAs($ghana)->test('payments.create', ['plan' => 'pro'])->assertSee('13 $ (7 500 FCFA) pour 30 jours');

        // Abonné : sa carte est marquée et propose de renouveler.
        $pro = User::factory()->create(['country' => 'TG', 'plan' => 'pro', 'plan_expires_at' => now()->addDays(5)]);
        $this->actingAs($pro)->get(route('plans'))->assertSee('data-my-plan', false)->assertSee('Renouveler')->assertSee('Active jusqu&#039;au 17/11/2026.', false);
    }

    public function test_the_administrator_sets_the_free_pause_and_the_period_of_each_formula(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Volt::actingAs($admin)->test('admin.settings')
            ->assertSet('plans.pro.period', 'month')
            ->assertSet('plans.pro.price_usd', 13)
            ->set('general.free_pause_minutes', 3)
            ->set('plans.agence.price_usd', 90)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame(3, $settings->int('free_pause_minutes'));
        $this->assertSame(90, $settings->get('plans')['agence']['price_usd']);
        $this->assertSame('month', $settings->get('plans')['agence']['period']);
    }

    private function spend(Project $project, float $fcfa): void
    {
        $project->agentRuns()->create(['message_id' => $project->messages()->create(['role' => 'user', 'content' => 'Demande'])->id])
            ->forceFill(['status' => 'completed', 'model_cost_usd' => $fcfa / 590])->save();
    }
}
