<?php

namespace Tests\Feature;

use App\Jobs\EraseProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_conditions_of_use_are_public_and_forbid_hacking_and_spam(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee("Conditions d'utilisation")
            ->assertSee('pirater')
            ->assertSee('spam')
            ->assertSee('hameçonnage')
            ->assertSee('30 jours')
            ->assertSee('droit togolais');
    }

    public function test_the_privacy_policy_says_that_free_requests_can_be_used_by_the_ai_providers(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Kekeli utilise aussi des offres gratuites de fournisseurs d\'intelligence artificielle, dont les conditions permettent d\'utiliser vos demandes et le contenu de vos projets pour améliorer leurs services', false)
            ->assertSee('dans la formule gratuite')
            ->assertSee('dans toutes les formules')
            ->assertSee('Google')
            ->assertSee('E2B')
            ->assertSee('Cloudflare')
            ->assertSee('Laravel Cloud')
            ->assertSee('2019-014');
    }

    public function test_the_home_page_links_to_both_pages(): void
    {
        $this->get('/')->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
    }

    public function test_registration_requires_accepting_the_conditions(): void
    {
        $this->get('/login')->assertSee('vous acceptez les', false)->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));

        $this->signUpWithCode('ama@exemple.tg', 'Ama', 'TG', terms: false)->assertHasErrors('terms');
        $this->assertSame(0, User::count());

        $this->signUpWithCode('ama2@exemple.tg', 'Ama', 'TG')->assertHasNoErrors();
        $this->assertNotNull(User::sole()->terms_accepted_at);
    }

    public function test_deleting_the_account_erases_projects_photos_and_payment_screenshots(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Restaurant']);
        Storage::disk('local')->put('paiements/'.$user->id.'/capture.png', 'octets');
        $user->payments()->create(['plan' => 'pro', 'amount_fcfa' => 7500, 'country' => 'TG', 'screenshot_path' => 'paiements/'.$user->id.'/capture.png']);

        Volt::actingAs($user)->test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertNull(User::find($user->id));
        Storage::disk('local')->assertMissing('paiements/'.$user->id.'/capture.png');
        Queue::assertPushed(EraseProject::class, fn (EraseProject $job) => $job->projectId === $project->id);
    }

    public function test_the_privacy_policy_names_every_ai_provider_that_receives_data(): void
    {
        $page = $this->get(route('legal.privacy'))->assertOk();
        foreach (['OpenAI', 'Google', 'OpenRouter', 'Cloudflare', 'Workers AI', 'E2B', 'Tavily', 'Exa', 'Brevo'] as $provider) {
            $page->assertSee($provider);
        }
        $this->get(route('legal.terms'))->assertSee("OpenAI d'abord", false)->assertSee('Cloudflare (Workers AI)')->assertDontSee('Mistral')->assertDontSee('Groq');
        // Groq n'est plus dans la liste des fournisseurs (offre gratuite trop petite pour l'agent).
        $page->assertDontSee('Groq');
        // Mistral n'a plus d'offre gratuite pour l'API : il ne reçoit aucune donnée.
        $page->assertDontSee('Mistral');
    }

    /**
     * Les pages légales suivent le fonctionnement actuel (finitions de la pull request 54) : OpenAI d'abord pour toutes
     * les formules, offres gratuites en secours même pour les payants, connexion par code, mémoire, reçus.
     */
    public function test_the_legal_pages_match_how_kekeli_works_today(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('14 novembre 2026')
            ->assertSee('pour toutes les formules')
            ->assertSee('Réglages → Mémoire')
            ->assertSee('Vos reçus')
            ->assertSee('5 minutes avec la formule gratuite')
            ->assertDontSee('écrivez-nous sur WhatsApp');
        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('14 novembre 2026')
            ->assertSee('avec un code envoyé à votre adresse électronique')
            ->assertSee('Mes reçus', false)
            ->assertDontSee('Vous gardez votre mot de passe secret.');
    }
}
