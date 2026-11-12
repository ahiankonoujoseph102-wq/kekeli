<?php

namespace Tests;

use App\Agent\FirstResponder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
