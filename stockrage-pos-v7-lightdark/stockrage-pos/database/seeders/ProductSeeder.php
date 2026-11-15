<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * The shop menu: three flavors in four sizes, plus the single flavors from the original guide. Names follow "<flavor> - <size>" so the POS
 * can show one picture card per flavor with a size picker.
 *
 * Prices are the shop's. The cost per set is only a rough guess (about 45% of the price) so that
 * Profit is not zero in the demo: set the real cost on each product (Products > Edit).
 */
class ProductSeeder extends Seeder
{
    /** A guess for how many pieces one order of a single flavor has. Change it in Products > Edit. */
    private const PIECES_PER_ORDER = 6;

    public function run(StockService $stock): void
    {
        $menu = [
            'Bacon N\' Cheese' => ['Barkada (12 pcs)' => 120, 'Family Tray (25 pcs)' => 240, 'Party Tray (50 pcs)' => 525, 'Solo (5 pcs)' => 55],
            'Mix' => ['Barkada (12 pcs)' => 170, 'Family Tray (25 pcs)' => 325, 'Party Tray (50 pcs)' => 625, 'Solo (6 pcs)' => 75],
            'Overload' => ['Barkada (12 pcs)' => 145, 'Family Tray (25 pcs)' => 290, 'Party Tray (50 pcs)' => 565, 'Solo (5 pcs)' => 65],
        ];

        // Single flavors (one size). Prices are the ones from the guide: change them in Products > Edit.
        $singles = ['Classic Takoyaki' => 50, 'Cheese Takoyaki' => 60, 'Crab Stick Takoyaki' => 60, 'Octobits' => 70];
        foreach ($singles as $name => $price) {
            $menu[$name] = ['' => $price];
        }

        foreach ($menu as $flavor => $sizes) {
            foreach ($sizes as $size => $price) {
                $name = $size === '' ? $flavor : "$flavor - $size";

                if ($existing = Product::where('name', $name)->first()) {
                    // single flavors: fill in "pieces per order" if it was never set
                    if ($size === '' && $existing->pieces === null) {
                        $existing->update(['pieces' => self::PIECES_PER_ORDER]);
                    }
                    continue;
                }

                $product = Product::create([
                    'name' => $name,
                    'category' => 'Takoyaki',
                    'price' => $price,
                    'cost_price' => round($price * 0.45, 2),
                    'pieces' => $size === '' ? self::PIECES_PER_ORDER : null,
                    'stock' => 0,
                    'low_stock_threshold' => 10,
                ]);

                // Opening stock goes through StockService so Stock History starts correct.
                $stock->change($product, 100, 'in', 'Opening stock');
            }
        }
    }
}
