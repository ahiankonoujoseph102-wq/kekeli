<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Jobs\SendAgentRun;
use App\Models\AgentRun;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Créer comme Lovable et Claude (15 novembre 2026) : bonus de bienvenue donné une seule fois, utilisé avant l'énergie
 * du jour ; plafond des comptes gratuits à 3 000 FCFA ; avertissement avant une création quand l'énergie ne suffit
 * visiblement pas.
 */
class WelcomeBonusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.settings.welcome_bonus_fcfa' => 500, 'kekeli.settings.usd_to_fcfa' => 600]);
    }

    private function spend(Project $project, float $usd): AgentRun
    {
        $message = $project->messages()->create(['role' => Message::ROLE_USER, 'content' => 'Une demande']);

        return tap($project->agentRuns()->create(['message_id' => $message->id]), fn (AgentRun $run) => $run->forceFill(['status' => AgentRun::STATUS_COMPLETED, 'model_cost_usd' => $usd, 'finished_at' => now()])->save());
    }

    public function test_a_new_account_gets_the_bonus_once_and_uses_it_before_the_energy_of_the_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00', 'UTC'));
        $user = User::factory()->create(['plan' => 'gratuite']);
        $energy = app(Energy::class);
        $this->assertSame(500.0, $energy->bonusFcfa($user));
        $this->assertSame(600.0, $energy->leftFcfa($user), '500 FCFA de bonus et 100 FCFA du jour');

        // 0,5 $ = 300 FCFA : tout vient du bonus, l'énergie du jour reste pleine.
        $project = $user->projects()->create(['name' => 'Formations']);
        $this->spend($project, 0.5);
        $this->assertSame(200.0, $energy->bonusLeftFcfa($user));
        $this->assertSame(40, $energy->bonusPercentLeft($user));
        $this->assertSame(100, $energy->percentLeft($user));

        // Le menu du compte : une ligne discrète tant qu'il reste du bonus.
        $this->actingAs($user)->get(route('projects.index'))->assertSee('data-account-bonus="40"', false)->assertSee('Bonus de bienvenue')->assertSee('40 % restants');

        // 0,4 $ = 240 FCFA : 200 FCFA de bonus, puis 40 FCFA de l'énergie du jour.
        $this->spend($project, 0.4);
        $this->assertSame(0.0, $energy->bonusLeftFcfa($user));
        $this->assertSame(60, $energy->percentLeft($user));
        $this->actingAs($user)->get(route('projects.index'))->assertDontSee('data-account-bonus', false);

        // Le lendemain : le bonus ne revient jamais, l'énergie du jour oui.
        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00', 'UTC'));
        $this->assertSame(0.0, $energy->bonusLeftFcfa($user));
        $this->assertSame(100.0, $energy->leftFcfa($user));
    }

    public function test_the_migration_gives_the_bonus_to_existing_accounts_once_and_raises_only_the_old_free_ceiling(): void
    {
        $old = User::factory()->create();
        $old->forceFill(['welcome_bonus_fcfa' => 0, 'welcome_bonus_at' => null])->save();
        $already = User::factory()->create();
        $already->forceFill(['welcome_bonus_fcfa' => 300, 'welcome_bonus_at' => now()->subDay()])->save();
        app(Settings::class)->set('free_daily_cap_fcfa', 1000);

        $migration = require database_path('migrations/2026_11_27_000001_welcome_bonus_and_free_cap.php');
        // Relancée deux fois : jamais de doublon.
        $migration->fill();
        $migration->fill();
        $this->assertSame(500.0, $old->fresh()->welcome_bonus_fcfa);
        $this->assertNotNull($old->fresh()->welcome_bonus_at);
        $this->assertSame(300.0, $already->fresh()->welcome_bonus_fcfa, 'jamais donné une deuxième fois');

        // Le plafond : l'ancienne valeur 1 000 laisse la place à 3 000 ; une valeur choisie à la main reste.
        $this->assertSame(3000.0, app(Settings::class)->float('free_daily_cap_fcfa'));
        app(Settings::class)->set('free_daily_cap_fcfa', 2500);
        $migration->fill();
        $this->assertSame(2500.0, app(Settings::class)->float('free_daily_cap_fcfa'));
    }

    public function test_no_bonus_when_the_setting_is_zero(): void
    {
        config(['kekeli.settings.welcome_bonus_fcfa' => 0]);
        $user = User::factory()->create();
        $this->assertNull($user->welcome_bonus_at);
        $this->assertSame(0.0, app(Energy::class)->bonusFcfa($user));
    }

    public function test_before_a_creation_kekeli_warns_when_the_energy_is_visibly_too_small_then_can_start_anyway(): void
    {
        Bus::fake([SendAgentRun::class]);
        config(['kekeli.settings.welcome_bonus_fcfa' => 0, 'kekeli.settings.creation_estimates_fcfa' => ['creer_site' => 200]]);
        $user = User::factory()->create(['country' => 'TG', 'plan' => 'gratuite']);
        $other = $user->projects()->create(['name' => 'Avant']);
        // 0,15 $ = 90 FCFA : il reste 10 FCFA, moins de la moitié des 200 FCFA d'un site.
        $this->spend($other, 0.15);

        $this->model->callsTool('creer_site');
        Volt::actingAs($user)->test('site-prompt')->set('description', 'Crée le site de mon restaurant Le Palmier à Lomé avec le menu et WhatsApp')->call('build');
        $chat = $user->projects()->where('id', '!=', $other->id)->sole();
        $question = $chat->agentRuns()->sole();
        $this->assertSame('energie', data_get($question->report, 'offer_reason'));
        $this->assertTrue((bool) $question->simple_chat, 'la phrase ne coûte rien');
        Bus::assertNotDispatched(SendAgentRun::class);
        $this->actingAs($user)->get(route('projects.show', $chat))
            ->assertSee('Il vous reste environ 5 %')
            ->assertSee('Commencer quand même')
            ->assertSee('data-energy-plans', false);

        Volt::actingAs($user)->test('projects.show', ['project' => $chat->fresh()])->call('acceptOffer', $question->id);
        Bus::assertDispatched(SendAgentRun::class);
        $this->assertContains('Commencer quand même', $chat->messages()->where('role', 'user')->pluck('content')->all());
    }
}
