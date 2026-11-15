<?php

namespace Tests\Feature;

use App\Agent\RunnerEventHandler;
use App\Models\AdminAlert;
use App\Models\AgentRun;
use App\Models\EmailLog;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Plafond OpenAI du jour : 10 $ depuis le 15 novembre 2026 (accord du porteur du projet), alertes de l'administrateur
 * à 80 % et à 100 % (une seule fois par seuil et par jour), et migration qui ne remplace que l'ancienne valeur 1.
 */
class OpenAiCeilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_administrator_is_told_once_at_80_and_once_at_100_percent(): void
    {
        Queue::fake();
        User::factory()->create(['is_admin' => true, 'email' => 'admin@exemple.tg']);
        $this->assertSame(10.0, app(Settings::class)->float('openai_daily_cap_usd'));
        $project = User::factory()->create()->projects()->make(['name' => 'Discussion']);
        $project->forceFill(['kind' => Project::KIND_CHAT])->save();
        $run = $project->agentRuns()->create(['purpose' => AgentRun::PURPOSE_QUESTION]);
        app(Settings::class)->set('model_prices', ['openai/gpt-6.1-terra' => ['input' => 1, 'output' => 5, 'cache' => 0.05]]);
        $spend = fn (float $usd) => app(RunnerEventHandler::class)->handle($run, ['type' => 'requete', 'fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'tokens_entree' => (int) round($usd * 1_000_000), 'tokens_sortie' => 0, 'requetes' => 1]);

        $spend(7.0);
        $this->assertSame(0, AdminAlert::count());
        $spend(1.5);
        $spend(0.1);
        $this->assertSame(1, AdminAlert::where('kind', 'openai_ceiling')->count(), 'une seule alerte à 80 %');
        $this->assertSame(1, EmailLog::where('type', 'openai_ceiling')->count());
        $spend(2.0);
        $this->assertSame(2, AdminAlert::where('kind', 'openai_ceiling')->count(), 'puis une à 100 %');
        $this->assertSame(2, EmailLog::where('type', 'openai_ceiling')->count());
    }

    public function test_the_migration_replaces_only_the_old_value_one(): void
    {
        $migration = require database_path('migrations/2026_11_28_000001_openai_daily_cap_ten_dollars.php');
        app(Settings::class)->set('openai_daily_cap_usd', 1.0);
        $migration->up();
        $this->assertSame(10.0, (float) json_decode((string) DB::table('settings')->where('key', 'openai_daily_cap_usd')->value('value'), true));

        app(Settings::class)->set('openai_daily_cap_usd', 3.5);
        $migration->up();
        $this->assertSame(3.5, app(Settings::class)->float('openai_daily_cap_usd'), 'une valeur choisie à la main est gardée');
    }
}
