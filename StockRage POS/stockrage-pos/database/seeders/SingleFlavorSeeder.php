<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SingleFlavorSeeder extends Seeder
{
    public const PRICES = [
        'Classic Takoyaki' => 50,
        'Cheese Takoyaki' => 60,
        'Crab Stick Takoyaki' => 60,
        'Octobits' => 70,
    ];

    public function run(StockService $stock): void
    {
        DB::transaction(function () use ($stock) {
            foreach (self::PRICES as $name => $price) {
                $product = Product::firstOrCreate(['name' => $name], [
                    'category' => 'Takoyaki',
                    'price' => $price,
                    'cost_price' => round($price * 0.45, 2),
                    'pieces' => 6,
                ]);

                if ($product->wasRecentlyCreated) {
                    $stock->change($product, 100, 'in', 'Restored single-flavor opening stock (pieces)');
                }
            }
        });
    }
}
