<?php

namespace Tests\Feature\Auth;

use App\Mail\KekeliMail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * « Mot de passe oublié » : seulement pour les comptes qui ont un mot de passe, par les e-mails de Kekeli (file,
 * journal) ; la page affiche toujours la même phrase.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** The link of the last e-mail « Changer votre mot de passe ». */
    private function resetUrl(): string
    {
        return Mail::sent(KekeliMail::class, fn (KekeliMail $mail) => $mail->title === 'Changer votre mot de passe')->last()->button['url'];
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested_only_for_an_account_with_a_password(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $withoutPassword = User::factory()->create(['password' => null]);

        Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');
        Volt::test('auth.forgot-password')->set('email', $withoutPassword->email)->call('sendPasswordResetLink');

        Mail::assertSent(KekeliMail::class, 1);
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $mail) => $mail->hasTo($user->email) && $mail->subjectLine === 'Changer votre mot de passe Kekeli');
        $this->assertSame(['password_reset'], EmailLog::pluck('type')->all());
        $this->assertTrue(EmailLog::sole()->priority);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');

        $this->get($this->resetUrl())->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');
        $token = basename((string) parse_url($this->resetUrl(), PHP_URL_PATH));

        Volt::test('auth.reset-password', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));
    }
}
