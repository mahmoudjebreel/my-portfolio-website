<?php

namespace Tests\Feature\Admin\Concerns;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * The framework's CSRF middleware with exactly one change: it does NOT skip
 * verification when running unit tests (PreventRequestForgery::runningUnitTests()
 * returns true under PHPUnit, so regular feature tests never exercise CSRF).
 * Everything else — same-origin check, token comparison, XSRF cookie decryption,
 * excluded paths — is the real implementation.
 */
class StrictPreventRequestForgery extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
