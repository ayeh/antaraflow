<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Validation\Rules\Password;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Strip the uncompromised() (HaveIBeenPwned HTTP) check so tests
        // are fast, deterministic, and offline-safe.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
