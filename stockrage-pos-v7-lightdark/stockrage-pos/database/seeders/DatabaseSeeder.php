<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     *   php artisan db:seed                       -> admin login, settings, 12 menu products
     *   php artisan db:seed --class=DemoSalesSeeder -> optional: fake sales so charts are not empty
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SettingSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
