<?php

use App\Models\Product;
use Database\Seeders\SingleFlavorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Product::exists()) {
            return;
        }

        DB::transaction(function () {
            app()->call([app(SingleFlavorSeeder::class), 'run']);
            Product::whereIn('name', array_keys(SingleFlavorSeeder::PRICES))
                ->whereNull('recipe')
                ->where('pieces', '>', 0)
                ->where('is_active', false)
                ->update(['is_active' => true]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restored flavors may have recorded sales and stock movements.');
    }
};
