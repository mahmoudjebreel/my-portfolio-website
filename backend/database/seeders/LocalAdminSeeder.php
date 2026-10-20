<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Local development only: creates (or resets the password of) the owner's admin account
 * in the main database, with fixed credentials. Not called by DatabaseSeeder.
 *
 *     php artisan db:seed --class=LocalAdminSeeder
 *
 * Refuses to run outside APP_ENV=local or against any database other than jebreel_portfolio.
 * Never ship these credentials: production admins are created with `php artisan admin:create`.
 */
class LocalAdminSeeder extends Seeder
{
    public const DATABASE = 'jebreel_portfolio';

    public const EMAIL = 'm.jebreel22@gmail.com';

    public const PASSWORD = 'Jebreel-Admin-2026';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalAdminSeeder only runs with APP_ENV=local.');
        }

        $database = DB::connection()->getDatabaseName();
        if ($database !== self::DATABASE) {
            throw new RuntimeException('LocalAdminSeeder only runs on '.self::DATABASE." (connected to {$database}).");
        }

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->name = $user->name ?: 'Mohammad Jebreel';
        $user->password = Hash::make(self::PASSWORD);
        // Set explicitly: is_admin is excluded from mass assignment.
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();

        $this->command?->info('Admin ready: '.self::EMAIL.' in '.$database.'.');
    }
}
