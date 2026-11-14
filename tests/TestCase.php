<?php

namespace Tests;

use App\Agent\FirstResponder;
use App\Mail\KekeliMail;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\Support\FakeFirstResponder;

abstract class TestCase extends BaseTestCase
{
    /** The simulated model of the first responder (by default, it calls the tool offered in the place). */
    protected FakeFirstResponder $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new FakeFirstResponder;
        $this->app->instance(FirstResponder::class, $this->model);

        // Les tests ne dépendent jamais des vrais réglages de la GitHub App de l'environnement : GitHub n'est pas réglé,
        // sauf dans les tests qui le règlent eux-mêmes (avec des valeurs inventées).
        config(['services.github.app_slug' => null, 'services.github.client_id' => null, 'services.github.client_secret' => null]);

        // Jamais un vrai e-mail ni la vraie connexion Google pendant les tests, même quand les réglages MAIL_* et
        // GOOGLE_* de l'environnement sont présents : envoi simulé, Google non réglé (sauf dans ses propres tests).
        config(['mail.default' => 'array', 'kekeli.admin_alert_email' => null, 'services.google.client_id' => null, 'services.google.client_secret' => null]);
    }

    /**
     * Creates an account like a visitor: e-mail, code received by e-mail (simulated sending), then the name, the
     * country and the conditions, on the single page « Se connecter ou créer un compte ».
     */
    protected function signUpWithCode(string $email, string $name = 'Ama Essai', string $country = 'TG', bool $terms = true): Testable
    {
        Mail::fake();
        $page = Volt::test('auth.login')->set('email', $email)->call('continueWithEmail')->assertHasNoErrors()->assertSet('step', 'code');
        $code = Mail::sent(KekeliMail::class)->last()->code;

        return $page->set('code', $code)->call('verifyCode')->assertHasNoErrors()->assertSet('step', 'name')
            ->set('name', $name)->set('country', $country)->set('terms', $terms)->call('createAccount');
    }

    /**
     * The real first responder, which asks the Runner (for the tests that check what is sent to it).
     */
    protected function useRealFirstResponder(): void
    {
        $this->app->forgetInstance(FirstResponder::class);
        $this->app->offsetUnset(FirstResponder::class);
    }
}
