<?php

namespace Tests\Feature;

use App\Mail\KekeliMail;
use App\Models\User;
use App\Support\Design;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nouvelle identité de Kekeli (docs/plan.md, partie 26.3) : fond clair et chaud, encre, une seule touche d'ambre,
 * polices libres servies par Kekeli, logo « K à la flamme » (docs/logo/).
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

    public function test_the_logo_is_the_k_with_the_flame_and_the_name_is_in_lower_case(): void
    {
        $symbol = file_get_contents(public_path('logo.svg'));
        foreach (['id="barre"', 'id="flamme"', 'id="jambe"', '#E3A008', '#1C1A17', '#FAF8F4', 'prefers-color-scheme:dark'] as $part) {
            $this->assertStringContainsString($part, $symbol);
        }
        $this->assertStringNotContainsString('<text', file_get_contents(public_path('logo-nom.svg')), 'le nom est en tracés');
        $this->assertStringContainsString('aria-label="kekeli"', (string) $this->blade('<x-app-logo />'));

        // Couleurs du logo en clair et en sombre : formes encre ou papier, flamme ambrée.
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/:root \{[^}]*--k-logo-forme: #1c1a17;[^}]*--k-logo-flamme: #e3a008;/s', $css);
        $this->assertMatchesRegularExpression('/\.dark \{[^}]*--k-logo-forme: #faf8f4;[^}]*--k-logo-flamme: #e3a008;/s', $css);
    }

    /**
     * Le logo se remplace en un geste : deux fichiers sources (public/logo.svg et public/logo-nom.svg), deux composants ;
     * le favicon, les icônes du site installable, l'image des e-mails et docs/logo/ en sont tirés (scripts/logo.js).
     */
    public function test_the_logo_lives_in_one_file_and_one_component(): void
    {
        foreach (['logo.svg', 'logo-nom.svg', 'logo.png', 'logo-email.png', 'favicon.ico', 'icones/icone-192.png', 'icones/icone-512.png', 'icones/icone-maskable-512.png', 'icones/apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }
        foreach (['symbole', 'symbole-sombre', 'symbole-noir', 'symbole-blanc', 'horizontal', 'horizontal-sombre', 'horizontal-noir', 'horizontal-blanc', 'icone-application'] as $variant) {
            $this->assertFileExists(base_path("docs/logo/kekeli-{$variant}.svg"));
        }
        $this->assertFileDoesNotExist(public_path('favicon.svg'));
        $this->assertStringContainsString("public_path('logo.svg')", file_get_contents(resource_path('views/components/app-logo-icon.blade.php')));

        // Plus aucun soleil : ni dessiné dans une vue, ni animé.
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            $view = $file->getPathname();
            if (str_ends_with($view, '.blade.php')) {
                $this->assertStringNotContainsString('M20 4.5v4.5', file_get_contents($view), basename($view).' dessine encore le soleil');
                $this->assertStringNotContainsString('kekeli-soleil', file_get_contents($view), basename($view).' anime encore le soleil');
            }
        }
        $this->assertStringNotContainsString('kekeli-soleil', file_get_contents(resource_path('css/app.css')));

        $home = $this->get('/')->assertOk();
        $home->assertSee('href="/logo.svg"', false)->assertSee('class="k-flamme"', false)->assertDontSee('id="flamme"', false);
        $this->assertStringContainsString('logo-email.png', (new KekeliMail('Sujet', 'Titre'))->render());
    }

    /**
     * Pendant le travail, la flamme du logo vacille doucement (seulement transform et opacity), et plus du tout quand le
     * téléphone demande moins de mouvement.
     */
    public function test_the_flame_flickers_while_kekeli_works(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.kekeli-logo-actif .k-flamme', $css);
        $this->assertMatchesRegularExpression('/@keyframes kekeli-flamme \{[^@]*transform[^@]*opacity/s', $css);
        $this->assertStringContainsString("'kekeli-logo-actif' => \$run->isActive()", file_get_contents(resource_path('views/livewire/projects/show.blade.php')));
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

        // Sobres comme Claude et ChatGPT : pas de « Erreur 404 » au-dessus du titre, un seul bouton vers l'accueil.
        $page = $this->get('/une-page-qui-n-existe-pas')->getContent();
        $this->assertStringNotContainsString('Erreur 404', $page);
        $this->assertSame(1, substr_count($page, ' data-error-home='));
        $this->assertStringContainsString('data-error-contact', $page);

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
