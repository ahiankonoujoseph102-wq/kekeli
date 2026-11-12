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
