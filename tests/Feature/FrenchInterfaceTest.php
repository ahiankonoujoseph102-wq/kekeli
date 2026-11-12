<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FrenchInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_presents_kekeli_in_french(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="fr"', false)
            ->assertSee('Que voulez-vous créer aujourd’hui ?')
            ->assertSee('Demandez à Kekeli de créer un site web pour…')
            ->assertSee('Créer un compte')
            ->assertDontSee('Version d\'essai en construction')
            ->assertDontSee('Voir les modèles de sites')
            ->assertDontSee('Décrivez');
    }

    public function test_the_home_page_offers_the_projects_to_a_logged_in_user(): void
    {
        $this->actingAs(User::factory()->create());

        // Connecté : l'accueil mène à « Nouveau », avec la même barre de navigation que toutes les pages connectées.
        $this->get('/')->assertRedirect(route('projects.index'));
        $this->get(route('projects.index'))->assertOk()
            ->assertSee('Que voulez-vous créer aujourd’hui ?')
            ->assertSee('data-navigation', false)
            ->assertSee('data-nav-new', false)
            ->assertSee('data-nav-toggle', false)
            ->assertDontSee('Mes projets');
    }

    public function test_the_account_pages_are_in_french(): void
    {
        $this->get('/register')->assertOk()->assertSee('Créer un compte')->assertSee('Mot de passe');
        $this->get('/login')->assertOk()->assertSee('Se connecter')->assertDontSee('Connectez-vous')->assertDontSee('Saisissez')->assertSee('Mot de passe oublié ?');
        $this->get('/forgot-password')->assertOk()->assertSee('Recevoir le lien de réinitialisation');
    }

    public function test_the_settings_pages_are_in_french(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings/profile')->assertOk()->assertSee('Réglages')->assertSee('Supprimer le compte');
        $this->get('/settings/password')->assertOk()->assertSee('Modifier le mot de passe');
        $this->get('/settings/appearance')->assertOk()->assertSee('Système');
    }

    public function test_a_wrong_password_shows_a_french_message(): void
    {
        $user = User::factory()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'mauvais-mot-de-passe')
            ->call('login')
            ->assertHasErrors('email')
            ->assertSee('Nous ne trouvons aucun compte avec cette adresse électronique et ce mot de passe.');
    }
}
