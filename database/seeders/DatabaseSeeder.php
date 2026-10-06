<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only the default SDS admin is created; colleges, offices, categories and accounts are set up
     * by the admin in the system. Sample data stays available on request:
     * php artisan db:seed --class=DemoAccountSeeder (or AnalyticsSeeder).
     */
    public function run(): void
    {
        $this->call([
            SdsAdminSeeder::class,
        ]);
    }
}
