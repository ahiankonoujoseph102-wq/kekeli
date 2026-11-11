<?php

namespace Tests\Feature;

use App\Models\User;
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
        $this->assertStringContainsString('r="7"', file_get_contents(resource_path('views/components/app-logo-icon.blade.php')));
        $this->assertStringContainsString('lowercase', file_get_contents(resource_path('views/components/app-logo.blade.php')));
        $this->assertStringContainsString('#e3a008', file_get_contents(public_path('favicon.svg')));
    }
}
