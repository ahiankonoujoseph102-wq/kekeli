<?php

namespace Tests\Feature;

use App\Mail\KekeliMail;
use App\Models\EmailLog;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Volt\Volt;
use Mockery;
use Tests\TestCase;

/**
 * Connexion et inscription comme Claude et ChatGPT : une seule page, « Continuer avec Google » (seulement quand Google
 * est réglé), puis l'e-mail et un code à 6 chiffres (ou le lien de l'e-mail) ; les anciens comptes gardent leur mot
 * de passe. L'envoi des e-mails et Google sont simulés.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function lastMail(): KekeliMail
    {
        return Mail::sent(KekeliMail::class)->last();
    }

    private function askCode(string $email)
    {
        return Volt::test('auth.login')->set('email', $email)->call('continueWithEmail')->assertHasNoErrors()->assertSet('step', 'code');
    }

    public function test_the_entry_page_has_the_email_first_and_google_only_when_it_is_set(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Se connecter ou créer un compte')
            ->assertSee('Continuer avec l’e-mail')
            ->assertSee('Se connecter avec un mot de passe')
            ->assertDontSee('Continuer avec Google')
            ->assertDontSee('data-login-separator', false);
        $this->get('/auth/google')->assertNotFound();

        config(['services.google.client_id' => 'identifiant-invente', 'services.google.client_secret' => 'secret-invente']);
        $html = $this->get('/login')->assertOk()->assertSee('Continuer avec Google')->getContent();
        $this->assertLessThan(strpos($html, 'Continuer avec l’e-mail'), strpos($html, 'Continuer avec Google'), 'Google en premier');
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_a_new_account_by_code_then_its_name_in_one_step(): void
    {
        $page = $this->askCode('Nouvelle@Exemple.tg');
        $mail = $this->lastMail();
        $this->assertTrue($mail->hasTo('nouvelle@exemple.tg'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);
        $this->assertSame('Votre code Kekeli : '.$mail->code, $mail->subjectLine);
        // Seule l'empreinte du code est gardée.
        $this->assertStringNotContainsString($mail->code, (string) LoginCode::sole()->code_hash);

        $page->set('code', $mail->code)->call('verifyCode')->assertHasNoErrors()->assertSet('step', 'name');
        $this->assertGuest();
        $page->set('name', 'Afi Mensah')->set('terms', true)->call('createAccount')->assertHasNoErrors()->assertRedirect(route('projects.index'));

        $user = User::where('email', 'nouvelle@exemple.tg')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame('TG', $user->country);
        // Le code ne sert qu'une fois ; l'e-mail de bienvenue part.
        $this->assertNotNull(LoginCode::sole()->used_at);
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->subjectLine === 'Bienvenue sur Kekeli' && $m->hasTo('nouvelle@exemple.tg'));
        $this->assertSame(['login_code', 'welcome'], EmailLog::orderBy('id')->pluck('type')->all());
    }

    public function test_an_existing_account_by_code_and_the_message_never_says_whether_it_exists(): void
    {
        $user = User::factory()->create(['email' => 'ama@exemple.tg']);
        $known = $this->askCode('ama@exemple.tg');
        $unknown = $this->askCode('personne@exemple.tg');
        $this->assertSame(
            str_replace('ama@exemple.tg', '', (string) $known->get('notice')),
            str_replace('personne@exemple.tg', '', (string) $unknown->get('notice')),
        );

        $code = Mail::sent(KekeliMail::class, fn ($m) => $m->hasTo('ama@exemple.tg'))->last()->code;
        $known->set('code', $code)->call('verifyCode')->assertHasNoErrors()->assertRedirect(route('projects.index'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNotSent(KekeliMail::class, fn ($m) => $m->subjectLine === 'Bienvenue sur Kekeli');
    }

    public function test_a_wrong_code_an_expired_code_and_too_many_tries(): void
    {
        User::factory()->create(['email' => 'ama@exemple.tg']);
        $page = $this->askCode('ama@exemple.tg');
        $good = $this->lastMail()->code;
        $wrong = $good === '000000' ? '111111' : '000000';

        $page->set('code', $wrong)->call('verifyCode')->assertHasErrors('code')->assertSee('Ce code n’est pas le bon');
        $this->assertGuest();

        // Trop d'essais : même le bon code ne marche plus, il faut en demander un nouveau.
        for ($i = 0; $i < 4; $i++) {
            $page->set('code', $wrong)->call('verifyCode');
        }
        $page->set('code', $good)->call('verifyCode')->assertHasErrors('code')->assertSee('trop de fois');
        $this->assertGuest();

        // Un code expiré (plus de 10 minutes).
        $page->call('resend');
        $fresh = $this->lastMail()->code;
        $this->travel(11)->minutes();
        $page->set('code', $fresh)->call('verifyCode')->assertHasErrors('code')->assertSee('a expiré');
        $this->assertGuest();
    }

    public function test_sendings_are_limited_against_abuse(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Volt::test('auth.login')->set('email', 'cible@exemple.tg')->call('continueWithEmail')->assertHasNoErrors();
        }
        Volt::test('auth.login')->set('email', 'cible@exemple.tg')->call('continueWithEmail')->assertHasErrors('email')->assertSee('Trop de codes demandés');
        Mail::assertSent(KekeliMail::class, 5);
        // Un nouveau code remplace les anciens.
        $this->assertSame(1, LoginCode::whereNull('used_at')->count());
    }

    public function test_the_link_of_the_email_signs_in_once(): void
    {
        $user = User::factory()->create(['email' => 'ama@exemple.tg']);
        $this->askCode('ama@exemple.tg');
        $url = $this->lastMail()->button['url'];

        $this->get($url)->assertRedirect(route('projects.index'));
        $this->assertAuthenticatedAs($user);
        Auth::logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_link_of_a_new_address_goes_to_the_name_step(): void
    {
        $this->askCode('nouveau@exemple.tg');
        $this->get($this->lastMail()->button['url'])->assertRedirect(route('login'));
        Volt::test('auth.login')->assertSet('step', 'name')->assertSet('email', 'nouveau@exemple.tg')
            ->set('name', 'Kossi')->set('terms', true)->call('createAccount')->assertHasNoErrors();
        $this->assertNotNull(User::where('email', 'nouveau@exemple.tg')->first());
    }

    public function test_an_older_account_still_signs_in_with_its_password_and_the_administrator_too(): void
    {
        $user = User::factory()->create(['email' => 'ancien@exemple.tg', 'password' => 'motdepasse-solide-2026']);
        $admin = User::factory()->create(['email' => 'admin@exemple.tg', 'password' => 'motdepasse-admin-2026', 'is_admin' => true]);

        $this->get('/login?etape=mot-de-passe')->assertOk()->assertSee('Mot de passe oublié ?');
        Volt::test('auth.login')->call('usePassword')->assertSet('step', 'password')
            ->set('email', 'ancien@exemple.tg')->set('password', 'motdepasse-solide-2026')->call('login')->assertHasNoErrors()->assertRedirect(route('projects.index'));
        $this->assertAuthenticatedAs($user);
        Auth::logout();

        Volt::test('auth.login')->set('email', 'admin@exemple.tg')->set('password', 'motdepasse-admin-2026')->call('login')->assertHasNoErrors();
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.emails'))->assertOk()->assertSee('E-mails (administration)');
        Auth::logout();

        // Les comptes avec mot de passe peuvent aussi passer par un code.
        $page = $this->askCode('ancien@exemple.tg');
        $page->set('code', $this->lastMail()->code)->call('verifyCode')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->password, 'le mot de passe d’un compte vérifié reste');
    }

    public function test_an_unproved_address_loses_its_old_password_when_its_owner_signs_in(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ama@exemple.tg', 'password' => 'mot-de-passe-de-quelqu-un']);
        $page = $this->askCode('ama@exemple.tg');
        $page->set('code', $this->lastMail()->code)->call('verifyCode')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->password);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    private function google(string $email, bool $verified = true, string $id = 'google-123'): void
    {
        config(['services.google.client_id' => 'identifiant-invente', 'services.google.client_secret' => 'secret-invente']);
        $profile = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => 'Ama Kodjo', 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/photo-inventee']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($profile);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_creates_a_new_account_after_the_country_and_the_conditions(): void
    {
        $this->google('ama.kodjo@gmail.com');
        $this->get('/auth/google/retour')->assertRedirect(route('login'));
        $this->assertGuest();

        Volt::test('auth.login')->assertSet('step', 'name')->assertSet('name', 'Ama Kodjo')
            ->set('terms', true)->call('createAccount')->assertHasNoErrors();
        $user = User::where('email', 'ama.kodjo@gmail.com')->sole();
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/photo-inventee', $user->avatar_url);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_links_an_existing_verified_account(): void
    {
        $user = User::factory()->create(['email' => 'ama@exemple.tg', 'password' => 'motdepasse-solide-2026']);
        $this->google('AMA@exemple.tg');
        $this->get('/auth/google/retour')->assertRedirect(route('projects.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->password, 'compte vérifié : rien d’autre ne change');
        $this->assertSame(1, User::count());
    }

    public function test_google_without_a_verified_address_is_refused(): void
    {
        $this->google('ama@exemple.tg', verified: false);
        $this->get('/auth/google/retour')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_account_without_password_has_no_password_tab(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user)->get(route('settings.profile'))->assertDontSee('data-settings-tab="settings.password"', false);
        $this->actingAs(User::factory()->create())->get(route('settings.profile'))->assertSee('data-settings-tab="settings.password"', false);
    }

    public function test_the_privacy_policy_explains_google_the_codes_the_emails_and_the_receipts(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Connexion avec Google')
            ->assertSee('votre photo de profil')
            ->assertSee('Brevo')
            ->assertSee('Vos reçus')
            ->assertSee('seuls vos reçus sont gardés pour la comptabilité');
    }
}
