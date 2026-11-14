<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Site installable depuis Chrome sur Android (« Ajouter à l'écran d'accueil »), ouvert sans barre d'adresse, avec une
 * page « hors connexion » qui ne montre jamais les données d'un compte (docs/plan.md, partie 26.24).
 */
class InstallableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_has_everything_chrome_needs_to_install_kekeli(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Kekeli', $manifest['name']);
        $this->assertSame('Kekeli', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertStringStartsWith('/', $manifest['start_url']);
        $this->assertSame('#FAF8F4', $manifest['background_color']);

        $purposes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $purposes[$icon['purpose']][] = $icon['sizes'];
        }
        $this->assertContains('192x192', $purposes['any']);
        $this->assertContains('512x512', $purposes['any']);
        $this->assertContains('512x512', $purposes['maskable']);
        [$width, $height] = getimagesize(public_path('icones/icone-maskable-512.png'));
        $this->assertSame([512, 512], [$width, $height]);
    }

    public function test_every_page_links_the_manifest_the_icons_and_both_theme_colours(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest" />', false)
            ->assertSee('href="/icones/apple-touch-icon.png"', false)
            ->assertSee('content="#faf8f4" media="(prefers-color-scheme: light)"', false)
            ->assertSee('content="#171513" media="(prefers-color-scheme: dark)"', false);
        $this->assertStringContainsString("register('/sw.js'", file_get_contents(resource_path('js/installable.js')));
    }

    public function test_the_offline_page_never_shows_account_data(): void
    {
        $user = User::factory()->create(['name' => 'Afi Mensah', 'email' => 'afi.mensah@exemple.tg']);
        $user->projects()->create(['name' => 'Restaurant Le Palmier']);

        $page = $this->actingAs($user)->get(route('offline'))->assertOk();
        $page->assertSee('Pas de connexion')->assertSee('data-offline-page', false)->assertSee('kekelilome3@gmail.com')
            ->assertDontSee('Afi Mensah')->assertDontSee('afi.mensah@exemple.tg')->assertDontSee('Restaurant Le Palmier')
            // Elle ne charge rien d'autre (aucun fichier qui ne serait pas en cache).
            ->assertDontSee('/build/', false);
    }

    public function test_the_service_worker_keeps_only_the_offline_page_and_the_logo(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("const A_GARDER = [PAGE_HORS_CONNEXION, '/logo.svg'];", $worker);
        $this->assertStringContainsString("credentials: 'omit'", $worker);
        // Jamais une réponse réelle mise en cache, seulement les pages ouvertes passent par lui.
        $this->assertStringNotContainsString('.put(', $worker);
        $this->assertStringContainsString("evenement.request.mode !== 'navigate'", $worker);
        $this->assertSame(1, substr_count($worker, 'addAll('));
    }
}
