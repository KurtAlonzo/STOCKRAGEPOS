<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ["Bacon N' Cheese - Solo (5 pcs)", 55],
            ["Bacon N' Cheese - Barkada (12 pcs)", 120],
            ["Bacon N' Cheese - Family Tray (25 pcs)", 240],
            ["Bacon N' Cheese - Party Tray (50 pcs)", 525],
            ['Overload - Solo (5 pcs)', 65],
            ['Overload - Barkada (12 pcs)', 145],
            ['Overload - Family Tray (25 pcs)', 290],
            ['Overload - Party Tray (50 pcs)', 565],
            ['Mix - Solo (6 pcs)', 75],
            ['Mix - Barkada (12 pcs)', 170],
            ['Mix - Family Tray (25 pcs)', 325],
            ['Mix - Party Tray (50 pcs)', 625],
        ];

        DB::transaction(function () use ($products): void {
            $menuIds = [];

            foreach ($products as [$name, $price]) {
                $product = Product::where('name', $name)
                    ->orderByDesc('is_active')
                    ->orderBy('id')
                    ->first() ?? new Product(['name' => $name]);

                $product->fill([
                    'category' => 'Takoyaki',
                    'price' => $price,
                    'is_active' => true,
                ]);
                if (! $product->exists) {
                    $product->fill(['stock' => 0, 'low_stock_threshold' => 10]);
                }
                $product->save();

                $menuIds[] = $product->id;
            }

            Product::active()->whereNotIn('id', $menuIds)->update(['is_active' => false]);
        });
    }
}
