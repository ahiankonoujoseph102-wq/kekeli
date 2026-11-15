<?php

namespace Tests\Feature;

use App\Agent\ProviderList;
use App\Agent\WorkersAiReserve;
use App\Models\ProviderUsage;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La réserve de Cloudflare Workers AI pour les images des clients (15 novembre 2026) : le texte ne peut jamais vider
 * les 10 000 neurones gratuits du jour dont les images ont besoin.
 */
class WorkersAiReserveTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_text_budget_keeps_every_allowed_image_of_the_day_and_a_margin(): void
    {
        app(Settings::class)->set('images_max_per_day', 150);
        // 10 000 − 150 × 58 − 500 = 800 neurones pour le texte.
        $this->assertSame(800, app(WorkersAiReserve::class)->textBudget());

        app(Settings::class)->set('images_max_per_day', 100);
        $this->assertSame(3700, app(WorkersAiReserve::class)->textBudget());
    }

    public function test_workers_ai_leaves_the_text_lists_once_its_budget_is_used(): void
    {
        config(['kekeli.providers_order' => 'gemini,workersai']);
        app(Settings::class)->set('images_max_per_day', 150);
        $providers = fn () => array_column(app(ProviderList::class)->forRunner(), 'fournisseur');
        // 20 000 tokens d'entrée et 15 000 de sortie avec GLM 4.7 Flash : 110 + 546 = 656 neurones, sous les 800.
        $usage = ProviderUsage::create(['day' => now('UTC')->toDateString(), 'provider' => 'workersai', 'model' => '@cf/zai-org/glm-4.7-flash']);
        $usage->forceFill(['input_tokens' => 20000, 'output_tokens' => 15000])->save();
        $this->assertTrue(app(WorkersAiReserve::class)->textAllowed());
        $this->assertContains('workersai', $providers());

        $usage->forceFill(['output_tokens' => 25000])->save();
        $this->assertFalse(app(WorkersAiReserve::class)->textAllowed());
        $this->assertNotContains('workersai', $providers());
        $this->assertContains('gemini', $providers());
    }

    public function test_yesterday_does_not_count(): void
    {
        $usage = ProviderUsage::create(['day' => now('UTC')->subDay()->toDateString(), 'provider' => 'workersai', 'model' => '@cf/zai-org/glm-4.7-flash']);
        $usage->forceFill(['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000])->save();
        $this->assertTrue(app(WorkersAiReserve::class)->textAllowed());
    }
}
