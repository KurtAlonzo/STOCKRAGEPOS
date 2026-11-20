<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        DB::transaction(fn () => $this->installMenu($stock));
    }

    private function installMenu(StockService $stock): void
    {
        $menu = [
            'Bacon N\' Cheese' => ['Barkada (12 pcs)' => 120, 'Family Tray (25 pcs)' => 240, 'Party Tray (50 pcs)' => 525, 'Solo (5 pcs)' => 55],
            'Mix' => ['Barkada (12 pcs)' => 170, 'Family Tray (25 pcs)' => 325, 'Party Tray (50 pcs)' => 625, 'Solo (6 pcs)' => 75],
            'Overload' => ['Barkada (12 pcs)' => 145, 'Family Tray (25 pcs)' => 290, 'Party Tray (50 pcs)' => 565, 'Solo (5 pcs)' => 65],
        ];

        $pools = [];
        foreach (["Bacon N' Cheese", 'Overload'] as $flavor) {
            $pool = Product::firstOrCreate(['stock_flavor' => $flavor], [
                'name' => $flavor, 'category' => 'Pieces', 'price' => 0,
            ]);
            if ($pool->wasRecentlyCreated) {
                $stock->change($pool, 100, 'in', 'Fresh flavor count (pieces)');
            }
            $pools[$flavor] = $pool->id;
        }

        foreach ($menu as $flavor => $sizes) {
            foreach ($sizes as $size => $price) {
                preg_match('/\((\d+) pcs\)/', $size, $matches);
                $pieces = (int) $matches[1];
                $recipe = $flavor === 'Mix'
                    ? [$pools["Bacon N' Cheese"] => intdiv($pieces + 1, 2), $pools['Overload'] => intdiv($pieces, 2)]
                    : [$pools[$flavor] => $pieces];

                $product = Product::firstOrCreate(['name' => "$flavor - $size"], [
                    'category' => 'Takoyaki', 'price' => $price, 'cost_price' => round($price * 0.45, 2),
                ]);
                $product->update(['recipe' => $recipe]);
            }
        }
    }
}
