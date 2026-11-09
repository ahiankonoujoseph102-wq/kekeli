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
            ->assertSee('Dans la formule gratuite, vos demandes et le contenu de vos projets peuvent être utilisés par les fournisseurs d\'intelligence artificielle pour améliorer leurs services', false)
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
        $this->get('/register')->assertSee("J'accepte les", false)->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));

        Volt::test('auth.register')
            ->set('name', 'Ama')
            ->set('email', 'ama@exemple.tg')
            ->set('password', 'motdepasse-solide-2026')
            ->set('password_confirmation', 'motdepasse-solide-2026')
            ->call('register')
            ->assertHasErrors('terms');
        $this->assertSame(0, User::count());

        Volt::test('auth.register')
            ->set('name', 'Ama')
            ->set('email', 'ama@exemple.tg')
            ->set('password', 'motdepasse-solide-2026')
            ->set('password_confirmation', 'motdepasse-solide-2026')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();
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
}
