<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Models\Project;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier « clics instantanés » (15 novembre 2026) : une page faisait environ 220 requêtes à la base, dont 160 lectures
 * du cache des réglages ; les réglages sont lus une fois par requête et l'énergie une fois par page. Chaque lien de la
 * navigation précharge sa page au survol ou au toucher, et un squelette s'affiche pendant qu'elle arrive.
 */
class InstantClicksTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_read_once_per_request_and_forgotten_when_changed(): void
    {
        $settings = app(Settings::class);
        $this->assertSame($settings, app(Settings::class), 'une seule instance par requête');
        $settings->get('plans');

        DB::enableQueryLog();
        foreach (range(1, 50) as $i) {
            $settings->get('plans');
            $settings->get('openai_daily_cap_usd');
        }
        $this->assertCount(0, DB::getQueryLog(), 'aucune requête de plus pour relire un réglage');

        $settings->set('openai_daily_cap_usd', 7.5);
        $this->assertSame(7.5, (float) $settings->get('openai_daily_cap_usd'), 'un réglage changé est relu');
        $settings->reset('openai_daily_cap_usd');
        $this->assertSame(10.0, (float) $settings->get('openai_daily_cap_usd'));
    }

    public function test_the_energy_is_read_again_outside_a_page_and_while_something_changes(): void
    {
        // Dans un travail, une commande ou une requête qui change quelque chose, l'énergie est toujours relue.
        $user = User::factory()->create(['country' => 'TG']);
        $project = $user->projects()->create(['name' => 'Essai', 'kind' => Project::KIND_CHAT]);
        $energy = app(Energy::class);
        $before = $energy->usedFcfa($user);
        $project->agentRuns()->create([])->forceFill(['status' => 'completed', 'model_cost_usd' => 0.01, 'finished_at' => now()])->save();
        $this->assertGreaterThan($before, $energy->usedFcfa($user));
    }

    public function test_a_page_asks_the_database_only_a_few_times(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $user->projects()->create(['name' => 'Discussion', 'kind' => Project::KIND_CHAT]);
        $this->actingAs($user)->get(route('projects.all'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('projects.all'))->assertOk();
        $count = count(DB::getQueryLog());
        // Les tests tournent « en console » : l'énergie y est toujours relue, le compte réel d'une page est plus bas
        // (scripts/compter-requetes.php). Avant le chantier : plus de 200 requêtes.
        $this->assertLessThan(80, $count, "$count requêtes");
    }

    public function test_the_navigation_preloads_its_pages_and_shows_a_skeleton(): void
    {
        $user = User::factory()->create(['country' => 'TG']);
        $page = $this->actingAs($user)->get(route('projects.all'))->assertOk();
        foreach (['data-nav-new', 'data-nav-projects', 'data-nav-library'] as $link) {
            $this->assertMatchesRegularExpression('/<a[^>]*wire:navigate\.hover[^>]*'.$link.'|<a[^>]*'.$link.'[^>]*wire:navigate\.hover/', $page->getContent(), $link);
        }
        $page->assertSee('<template data-squelettes>', false)
            ->assertSee('data-squelette="discussion"', false)
            ->assertSee('data-squelette="page"', false)
            ->assertSee('Que voulez-vous créer aujourd’hui ?');
        $this->assertStringContainsString("dispatchEvent(new MouseEvent('mouseenter'))", file_get_contents(resource_path('js/navigation.js')));
    }
}
