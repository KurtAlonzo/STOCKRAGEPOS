<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\BundleService;
use App\Support\MenuArt;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private BundleService $bundles)
    {
    }

    public function index(): View
    {
        // Only active products are sold; out-of-stock ones are shown grayed out.
        $products = Product::query()->active()->orderBy('category')->orderBy('name')->get();

        // Flat list the browser uses for names, prices and live stock.
        $catalog = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'stock' => $p->stock,
            'recipe' => $p->recipe,
            'threshold' => $p->low_stock_threshold,
            'image' => $p->image_url,
            'art' => MenuArt::kind($p->name),
        ])->values();

        // The POS sells only by size: Solo, Barkada, Family Tray, Party Tray (whatever sizes the menu has). No loose flavor cards.
        $art = ['solo' => 'classic', 'barkada' => 'bacon', 'family-tray' => 'overload', 'party-tray' => 'mix'];
        $sizes = $this->bundles->trays()->map(function (array $t) use ($art) {
            $t['art'] = $art[$t['key']] ?? 'mix';

            return $t;
        })->values();

        return view('pos.index', [
            'catalog' => $catalog,
            'sizes' => $sizes,
            'products' => $products,
        ]);
    }
}
