<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data: ~75 days of fake sales (through the real checkout code),
 * so the dashboard charts and reports are not empty during the demo.
 *
 *   php artisan db:seed --class=DemoSalesSeeder
 */
class DemoSalesSeeder extends Seeder
{
    public function run(SaleService $sales, StockService $stock): void
    {
        $this->call([AdminUserSeeder::class, SettingSeeder::class, ProductSeeder::class]);

        $cashierId = \App\Models\User::query()->orderBy('id')->value('id');

        if (Sale::count() > 0) {
            $this->command?->warn('Sales already exist. Skipping demo data.');

            return;
        }

        mt_srand(42); // same demo data every time

        $products = Product::active()->get();
        $methods = ['cash', 'cash', 'cash', 'gcash', 'card'];

        for ($daysAgo = 75; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo)->startOfDay();
            $count = mt_rand(5, 14) + ($day->isWeekend() ? mt_rand(3, 8) : 0);

            for ($i = 0; $i < $count; $i++) {
                $at = $day->copy()->setTime(mt_rand(10, 20), mt_rand(0, 59), mt_rand(0, 59));
                $lines = [];

                foreach ($products->shuffle()->take(mt_rand(1, 3)) as $product) {
                    $qty = mt_rand(1, 4);
                    $product->refresh();

                    if ($product->stock < $qty) {
                        $stock->change($product, 150, 'in', 'Restock', $at->copy()->subHour());
                    }
                    $lines[] = ['product_id' => $product->id, 'qty' => $qty];
                }

                $total = collect($lines)->sum(fn ($l) => $l['qty'] * (float) $products->firstWhere('id', $l['product_id'])->price);
                $paid = ceil($total / 50) * 50;

                $sale = $sales->checkout($lines, $paid, $methods[array_rand($methods)], $at, $cashierId);

                // A few voided sales, so Void shows up in Stock History.
                if (mt_rand(1, 40) === 1) {
                    $sales->void($sale, 'Customer cancelled the order', $cashierId);
                }
            }
        }

        // Leave one product low and one out of stock so the warnings are visible in the demo.
        $low = Product::where('name', "Bacon N' Cheese - Solo (5 pcs)")->first();
        $out = Product::where('name', 'Overload - Solo (5 pcs)')->first();
        if ($low) {
            $stock->change($low, 8 - $low->fresh()->stock, 'adjustment', 'Demo: low stock');
        }
        if ($out) {
            $stock->change($out, -$out->fresh()->stock, 'adjustment', 'Demo: sold out');
        }
    }
}
