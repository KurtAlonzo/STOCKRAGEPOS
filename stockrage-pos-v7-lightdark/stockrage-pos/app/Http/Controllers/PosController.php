<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        // Only active products are sold; out-of-stock ones are shown grayed out.
        $products = Product::query()->active()->orderBy('category')->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'price' => (float) $p->price,
                'stock' => $p->stock,
                'threshold' => $p->low_stock_threshold,
                'image' => $p->image_url,
            ])->values();

        return view('pos.index', ['products' => $products]);
    }
}
