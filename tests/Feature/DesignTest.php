<?php

namespace Tests\Feature;

use App\Mail\KekeliMail;
use App\Models\User;
use App\Support\Design;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nouvelle identité de Kekeli (docs/plan.md, partie 26.3) : fond clair et chaud, encre, une seule touche d'ambre,
 * polices libres servies par Kekeli, soleil simplifié.
 */
class DesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_colours_and_fonts_of_the_new_identity_are_defined(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("'Inter Variable'", $css);
        $this->assertStringContainsString("'Instrument Serif'", $css);
        $this->assertStringContainsString('--color-zinc-50: #faf8f4;', $css);
        $this->assertStringContainsString('--color-zinc-950: #171513;', $css);
        $this->assertStringContainsString('--color-amber-500: #e3a008;', $css);
        // Les boutons principaux sont couleur encre, pas orange.
        $this->assertStringContainsString('--color-accent: var(--color-zinc-900);', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_the_pages_use_the_warm_background_and_no_outside_font_service(): void
    {
        $home = $this->get('/')->assertOk();
        $home->assertSee('kekeli-fond', false)
            ->assertSee('kekeli-titre', false)
            ->assertDontSee('fonts.bunny.net', false)
            ->assertSee('content="#faf8f4"', false);

        $this->get(route('login'))->assertOk()->assertSee('kekeli-fond', false);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertSee('kekeli-fond', false);
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('kekeli-fond', false);
    }

    public function test_the_send_button_and_the_client_messages_are_ink_coloured(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Le Palmier']);
        $project->messages()->create(['role' => 'user', 'content' => 'Un site pour mon restaurant.']);

        $html = $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent();

        $this->assertStringNotContainsString('bg-amber-500 text-white', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*bg-zinc-900[^"]*"[^>]*data-prompt-send/s', $html);
        $this->assertStringContainsString('rounded-3xl rounded-br-lg bg-zinc-100', $html);
    }

    public function test_the_sun_logo_is_simplified_and_the_name_is_in_lower_case(): void
    {
        $this->assertStringContainsString('r="7"', file_get_contents(public_path('logo.svg')));
        $this->assertStringContainsString('lowercase', file_get_contents(resource_path('views/components/app-logo.blade.php')));
        $this->assertStringContainsString('#e3a008', file_get_contents(public_path('logo.svg')));
    }

    /**
     * Le logo provisoire se remplace en un geste : un seul fichier (public/logo.svg), un seul composant
     * (<x-app-logo-icon>) ; le favicon et l'image des e-mails en sont tirés (scripts/logo.js).
     */
    public function test_the_logo_lives_in_one_file_and_one_component(): void
    {
        $this->assertFileExists(public_path('logo.svg'));
        $this->assertFileExists(public_path('logo.png'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileDoesNotExist(public_path('favicon.svg'));
        $this->assertStringContainsString("public_path('logo.svg')", file_get_contents(resource_path('views/components/app-logo-icon.blade.php')));

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $view = $file->getPathname();
            if (str_ends_with($view, '.blade.php') && ! str_ends_with($view, 'app-logo-icon.blade.php')) {
                $this->assertStringNotContainsString('M20 4.5v4.5', file_get_contents($view), basename($view).' dessine le soleil au lieu d’utiliser le composant');
            }
        }

        $home = $this->get('/')->assertOk();
        $home->assertSee('href="/logo.svg"', false)->assertSee('M20 4.5v4.5', false);
        $this->assertStringContainsString('logo.png', (new KekeliMail('Sujet', 'Titre'))->render());
    }

    /**
     * Le système de design est défini à un seul endroit (resources/css/app.css) ; les e-mails et le reçu PDF reprennent
     * exactement les mêmes couleurs (App\Support\Design).
     */
    public function test_emails_and_receipts_use_the_same_tokens_as_the_site(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match('/:root \{(.*?)\}/s', $css, $light);
        preg_match('/\n\.dark \{(.*?)\}/s', $css, $dark);

        foreach (Design::LIGHT as $token => $value) {
            $this->assertStringContainsString("--k-$token: $value;", $light[1], "clair : $token");
        }
        foreach (Design::DARK as $token => $value) {
            $this->assertStringContainsString("--k-$token: $value;", $dark[1], "sombre : $token");
        }
        foreach (['--radius-xl: 14px;', '--shadow-md:', '--ease-kekeli:', '--animate-apparition:', '.kekeli-vide', '.kekeli-squelette', '.kekeli-scintille', '.kekeli-carte'] as $piece) {
            $this->assertStringContainsString($piece, $css, $piece);
        }
        $this->assertStringContainsString(Design::LIGHT['bouton'], (new KekeliMail('Sujet', 'Titre', button: ['label' => 'Ouvrir', 'url' => 'https://exemple.tg']))->render());
    }

    /** Contrastes WCAG AA (4,5 pour 1) des textes, en clair comme en sombre. */
    public function test_the_text_colours_are_readable_in_light_and_dark_mode(): void
    {
        foreach ([Design::LIGHT, Design::DARK] as $mode) {
            foreach (['texte', 'texte-doux', 'accent-texte'] as $text) {
                foreach (['fond', 'surface', 'surface-douce'] as $background) {
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($mode[$text], $mode[$background]), "$text sur $background");
                }
            }
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($mode['bouton-texte'], $mode['bouton']), 'bouton');
        }
    }

    public function test_animations_are_light_and_respect_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match_all('/@keyframes kekeli-[a-z]+ \{(.*?)\n    \}/s', $css, $keyframes);
        $this->assertNotEmpty($keyframes[1]);
        foreach ($keyframes[1] as $frames) {
            // Seulement opacity, transform et background-position : rien qui recalcule la mise en page.
            preg_match_all('/([a-z-]+):/', $frames, $properties);
            $this->assertEmpty(array_diff($properties[1], ['opacity', 'transform', 'background-position']), $frames);
        }
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\).*animation-duration: 0\.01ms/s', $css);
        $this->assertStringNotContainsString('framer-motion', file_get_contents(base_path('package.json')));
    }

    public function test_error_pages_have_the_design_and_the_contact(): void
    {
        $this->get('/une-page-qui-n-existe-pas')->assertNotFound()
            ->assertSee('kekeli-fond', false)->assertSee('data-error-page="404"', false)
            ->assertSee('Cette page n’existe pas')->assertSee('kekelilome3@gmail.com')
            ->assertSee("+228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31", false);

        foreach ([403, 419, 429, 500, 503] as $code) {
            $html = view("errors.$code")->render();
            $this->assertStringContainsString('data-error-page="'.$code.'"', $html);
            $this->assertStringContainsString('kekelilome3@gmail.com', $html);
        }
    }

    private function contrast(string $a, string $b): float
    {
        $luminance = function (string $hex): float {
            $channels = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
            $channels = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }
}
