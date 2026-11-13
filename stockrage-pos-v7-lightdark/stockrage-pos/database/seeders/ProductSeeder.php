<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        $products = [
            // name, price, cost (what one serving costs you), low-stock alert
            ['Classic Takoyaki', 50, 22, 20],
            ['Cheese Takoyaki', 60, 28, 20],
            ['Crab Stick Takoyaki', 60, 27, 15],
            ['Octobits', 70, 32, 15],
            ['Overload Takoyaki', 85, 40, 10],
        ];

        foreach ($products as [$name, $price, $cost, $alert]) {
            if (Product::where('name', $name)->exists()) {
                continue;
            }

            $product = Product::create([
                'name' => $name,
                'category' => 'Takoyaki',
                'price' => $price,
                'cost_price' => $cost,
                'stock' => 0,
                'low_stock_threshold' => $alert,
            ]);

            // Opening stock goes through StockService so Stock History starts correct.
            $stock->change($product, 100, 'in', 'Opening stock');
        }
    }
}
