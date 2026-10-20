<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only reviewed, approved initial data belongs here (added later, e.g.
     * technologies and categories). It must never create users: the admin
     * account is created by its owner (planned `php artisan admin:create`).
     * Model factories are test-only and refuse to run outside "testing".
     */
    public function run(): void
    {
        //
    }
}
