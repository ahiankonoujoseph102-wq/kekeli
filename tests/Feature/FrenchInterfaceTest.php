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
            ->assertSee('Kekeli, l&#039;agent IA qui code pour vous', false)
            ->assertSee('Créer mon compte gratuit');
    }

    public function test_the_home_page_offers_the_projects_to_a_logged_in_user(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertOk()->assertSee('Ouvrir mes projets');
    }

    public function test_the_account_pages_are_in_french(): void
    {
        $this->get('/register')->assertOk()->assertSee('Créer un compte')->assertSee('Mot de passe');
        $this->get('/login')->assertOk()->assertSee('Connectez-vous à votre compte')->assertSee('Mot de passe oublié ?');
        $this->get('/forgot-password')->assertOk()->assertSee('Recevoir le lien de réinitialisation');
    }

    public function test_the_settings_pages_are_in_french(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings/profile')->assertOk()->assertSee('Réglages')->assertSee('Supprimer le compte');
        $this->get('/settings/password')->assertOk()->assertSee('Modifier le mot de passe');
        $this->get('/settings/appearance')->assertOk()->assertSee('Comme le téléphone');
    }

    public function test_a_wrong_password_shows_a_french_message(): void
    {
        $user = User::factory()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'mauvais-mot-de-passe')
            ->call('login')
            ->assertHasErrors('email')
            ->assertSee('Ces identifiants ne correspondent à aucun compte.');
    }
}
