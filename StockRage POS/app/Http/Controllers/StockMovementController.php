<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:'.implode(',', StockMovement::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = StockMovement::with('product')->latest('id');

        if ($id = $request->query('product_id')) {
            $query->where('product_id', $id);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return view('stock-history.index', [
            'movements' => $query->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'types' => StockMovement::TYPES,
        ]);
    }
}
