<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finitions du design : le bloc « Compte administrateur d'essai » au-dessus de l'aperçu a le style du reste
 * (icônes discrètes, même police, bloc propre une fois ouvert), jamais le triangle de dépliage brut.
 */
class TestAdminBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_test_account_block_has_the_style_of_the_design(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->make(['name' => 'Pressing Éclat']);
        $project->forceFill([
            'kind' => Project::KIND_WEBAPP,
            'app_stack' => 'laravel',
            'app_template' => 'socle',
            'app_admin_email' => 'admin@essai.kekeli.test',
            'app_admin_password' => 'mot-de-passe-invente',
        ])->save();

        $html = preg_replace('/<!--.*?-->/s', '', $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->getContent());
        $start = strrpos(substr($html, 0, strpos($html, 'data-test-admin')), '<details');
        $block = substr($html, $start, 4000);

        // Le triangle du navigateur est caché ; une clé à gauche, une flèche qui tourne à droite.
        $this->assertStringContainsString('list-none', $block);
        $this->assertMatchesRegularExpression('/\[&(amp;)?::-webkit-details-marker\]:hidden/', $block);
        $this->assertStringContainsString('group-open:rotate-180', $block);
        $this->assertSame(2, substr_count(substr($block, 0, strpos($block, '</summary>')), '<svg'));
        // Même police que le reste : plus de texte en police de code.
        $this->assertStringNotContainsString('<code', $block);
        $this->assertStringContainsString('<dt class="text-zinc-500 dark:text-zinc-400">Adresse e-mail</dt>', $block);
        $this->assertStringContainsString('admin@essai.kekeli.test', $block);
        // Mot de passe caché par défaut, montré seulement à la demande.
        $this->assertStringContainsString('x-show="voir" x-cloak class="break-all" data-test-password', $block);
        $this->assertStringContainsString('••••••••', $block);
        $this->assertStringContainsString('dark:bg-zinc-900', $block);
    }
}
