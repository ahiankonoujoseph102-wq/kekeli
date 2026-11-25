<?php

namespace Tests\Feature;

use App\Agent\Costs;
use App\Agent\FreeModel;
use App\Agent\ModelRouter;
use App\Agent\ProviderList;
use App\Models\AdminAlert;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FreeModel, le dernier renfort de Kekeli (décision du porteur du projet du 25 novembre 2026) : en tout dernier pour les
 * discussions, juste avant OpenAI pour les créations et les documents, liste fermée de modèles, un million de tokens par
 * mois avec alertes à 80 % et à 100 %, interrupteur dans l'administration.
 */
class FreeModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kekeli.settings.freemodel_monthly_tokens' => 1000000]);
    }

    private function names(array $list): array
    {
        return array_map(fn ($e) => $e['fournisseur'].'/'.$e['modele'], $list);
    }

    private function usual(): array
    {
        return [
            ['fournisseur' => 'openai', 'modele' => 'gpt-6.1-terra', 'remise_a_zero' => 'x'],
            ['fournisseur' => 'gemini', 'modele' => 'gemini-3.5-flash-lite', 'remise_a_zero' => 'x'],
        ];
    }

    public function test_freemodel_comes_last_for_a_discussion_and_before_openai_for_a_creation(): void
    {
        $user = User::factory()->create();
        $router = app(ModelRouter::class);

        $site = $this->names($router->route($user, 'site', $this->usual()));
        $this->assertSame('gemini/gemini-3.5-flash-lite', $site[0]);
        $this->assertSame(['freemodel/claude-sonnet-5-5', 'freemodel/gpt-6.1-sol', 'freemodel/glm-5.3-flash', 'openai/gpt-6.1-terra'], array_slice($site, -4));

        $document = $this->names($router->route($user, 'document', $this->usual()));
        $this->assertSame(['freemodel/claude-sonnet-5-5', 'freemodel/glm-5.3-flash', 'openai/gpt-6.1-terra'], array_slice($document, -3));

        $answer = $this->names($router->route($user, 'reponse_simple', $this->usual()));
        $this->assertSame(['openai/gpt-6.1-terra', 'freemodel/glm-5.3-flash', 'freemodel/deepseek-v4.1-flash'], array_slice($answer, -3));
    }

    public function test_only_the_closed_list_and_the_switch_of_the_administration(): void
    {
        $user = User::factory()->create();
        app(Settings::class)->set('freemodel_routes', ['construction' => ['claude-opus-5-5', 'glm-5.3-flash']]);
        $this->assertSame(['glm-5.3-flash'], app(FreeModel::class)->models('construction'));
        $this->assertTrue(ProviderList::isAllowed('freemodel', 'claude-sonnet-5-5'));
        $this->assertFalse(ProviderList::isAllowed('freemodel', 'claude-opus-5-5'));

        app(Settings::class)->set('model_providers', ['openai' => true, 'gratuits' => true, 'freellmapi' => true, 'freemodel' => false]);
        $this->assertNotContains('freemodel', array_column(app(ModelRouter::class)->route($user, 'site', $this->usual()), 'fournisseur'));
    }

    public function test_the_allowance_of_the_month_is_counted_alerted_and_stops_freemodel(): void
    {
        $user = User::factory()->create();
        $freeModel = app(FreeModel::class);
        $this->assertSame(950000, $freeModel->tokensLeft());

        ProviderUsage::create(['day' => now('UTC')->toDateString(), 'provider' => 'freemodel', 'model' => 'claude-sonnet-5-5'])
            ->forceFill(['input_tokens' => 700000, 'output_tokens' => 120000, 'reasoning_tokens' => 0])->save();
        // Le mois précédent ne compte pas.
        ProviderUsage::create(['day' => now('UTC')->subMonthNoOverflow()->startOfMonth()->toDateString(), 'provider' => 'freemodel', 'model' => 'glm-5.3-flash'])
            ->forceFill(['input_tokens' => 900000])->save();

        $this->assertSame(820000, $freeModel->tokensUsedThisMonth());
        $freeModel->checkAlerts();
        $freeModel->checkAlerts();
        $this->assertSame(1, AdminAlert::query()->where('kind', 'freemodel_allowance')->count(), 'une seule alerte à 80 %');
        $this->assertNotEmpty($freeModel->entries('site'));

        ProviderUsage::query()->where('model', 'claude-sonnet-5-5')->update(['output_tokens' => 300000]);
        $freeModel->checkAlerts();
        $this->assertSame(2, AdminAlert::query()->where('kind', 'freemodel_allowance')->count());
        $this->assertSame([], $freeModel->entries('site'));
        $this->assertNotContains('freemodel', array_column(app(ModelRouter::class)->route($user, 'site', $this->usual()), 'fournisseur'));
    }

    public function test_the_energy_counts_the_published_price_of_freemodel(): void
    {
        $cost = app(Costs::class)->modelCost('freemodel', 'claude-sonnet-5-5', 1_000_000, 1_000_000, 0, 0);
        $this->assertEqualsWithDelta(9.0, $cost, 0.0001);
    }

    public function test_cloudflare_models_retired_on_20_november_are_no_longer_allowed(): void
    {
        $this->assertFalse(ProviderList::isAllowed('workersai', '@cf/zai-org/glm-5.3-flash'));
        $this->assertFalse(ProviderList::isAllowed('workersai', '@cf/qwen/qwen3-30b-a3b-fp8'));
        $this->assertTrue(ProviderList::isAllowed('workersai', '@cf/zai-org/glm-4.7-flash'));
    }
}
