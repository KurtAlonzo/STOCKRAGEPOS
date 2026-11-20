<?php

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\ProductSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('stock_flavor')->nullable()->unique();
            $table->json('recipe')->nullable();
        });
        Schema::table('sale_items', fn (Blueprint $table) => $table->json('stock_used')->nullable());

        if (Product::exists()) {
            app(ProductSeeder::class)->run(app(StockService::class));
            Product::whereNull('stock_flavor')->whereNull('recipe')->update(['is_active' => false]);
        }
        $admin = User::where('email', config('pos.admin_email'))->first();
        if (! $admin && User::count() === 1) {
            $admin = User::first();
        }
        if ($admin) {
            Setting::set('register_admin_id', $admin->id);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a pre-migration backup to revert the inventory unit change.');
    }
};
