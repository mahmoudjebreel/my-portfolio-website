<?php

namespace Tests\Feature\Database;

use RuntimeException;
use Tests\TestCase;

/**
 * Proves the guard in Tests\TestCase refuses a non-test database.
 *
 * This class deliberately uses NO database traits, so even if the guard were broken,
 * re-running setUpTraits() here could not migrate or wipe anything. No query is executed.
 */
class TestDatabaseGuardRejectsTest extends TestCase
{
    public function test_guard_rejects_the_development_database(): void
    {
        config(['database.connections.mysql.database' => 'jebreel_portfolio']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests');

        $this->setUpTraits();
    }

    public function test_guard_rejects_a_non_mysql_default_connection(): void
    {
        config(['database.default' => 'sqlite']);

        $this->expectException(RuntimeException::class);

        $this->setUpTraits();
    }

    public function test_guard_rejects_a_database_url(): void
    {
        config(['database.connections.mysql.url' => 'mysql://someone@127.0.0.1/jebreel_portfolio']);

        $this->expectException(RuntimeException::class);

        $this->setUpTraits();
    }
}
