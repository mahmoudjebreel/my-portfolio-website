<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Deliberately WITHOUT RefreshDatabase: this test drives the migrator itself.
 * The guard in Tests\TestCase guarantees it can only touch jebreel_portfolio_test.
 */
class MigrationRollbackTest extends TestCase
{
    public function test_every_migration_rolls_back_and_re_applies_cleanly(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->assertApplicationTablesPresent(true);
        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));

        // migrate:fresh runs everything in a single batch, so one rollback undoes it all.
        $this->artisan('migrate:rollback')->assertSuccessful();
        $this->assertApplicationTablesPresent(false);
        $this->assertSame(['migrations'], Schema::getTableListing(DB::connection()->getDatabaseName(), false));

        $this->artisan('migrate')->assertSuccessful();
        $this->assertApplicationTablesPresent(true);
        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));
    }

    public function test_the_is_admin_migration_rolls_back_on_its_own(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();

        // Roll back the 14 newest migrations (17 ... 4): users stays, is_admin goes.
        $this->artisan('migrate:rollback', ['--step' => 14])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'is_admin'));
        $this->assertFalse(Schema::hasTable('categories'));

        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('users', 'is_admin'));
        $this->assertApplicationTablesPresent(true);
    }

    private function assertApplicationTablesPresent(bool $present): void
    {
        foreach (SchemaTest::APPLICATION_TABLES as $table) {
            $this->assertSame($present, Schema::hasTable($table), "Table [{$table}] should ".($present ? 'exist' : 'be gone'));
        }
    }
}
