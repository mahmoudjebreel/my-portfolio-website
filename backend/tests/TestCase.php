<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public const TEST_DATABASE = 'jebreel_portfolio_test';

    /**
     * Hard guard: tests may only ever touch the dedicated MySQL test database.
     *
     * setUpTraits() runs inside parent::setUp() after the application has booted
     * but BEFORE traits such as RefreshDatabase migrate/wipe anything, so a
     * misconfigured environment (cached config, wrong phpunit.xml, DB_URL, ...)
     * aborts the test before a single query can reach the development database.
     */
    protected function setUpTraits()
    {
        $default = config('database.default');
        $database = config('database.connections.mysql.database');
        $url = config('database.connections.mysql.url');

        if ($default !== 'mysql' || $database !== self::TEST_DATABASE || ! empty($url)) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests: expected connection "mysql" on database "%s" (no DB_URL), got connection "%s" on database "%s".',
                self::TEST_DATABASE,
                (string) $default,
                (string) $database,
            ));
        }

        return parent::setUpTraits();
    }
}
