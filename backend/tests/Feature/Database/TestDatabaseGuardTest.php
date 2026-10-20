<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_the_dedicated_mysql_test_database(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('jebreel_portfolio_test', config('database.connections.mysql.database'));
        $this->assertSame('jebreel_portfolio_test', DB::connection()->getDatabaseName());

        // Ask the server itself, not only Laravel's config.
        $this->assertSame('jebreel_portfolio_test', DB::scalar('SELECT DATABASE()'));
    }
}
