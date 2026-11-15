<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     *   php artisan db:seed                       -> admin login, settings, the 12-item menu (3 flavors x 4 sizes)
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
