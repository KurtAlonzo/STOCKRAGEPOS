<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales)
    {
    }

    /** Sales History: search by sale ID, filter by date range. */
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:completed,voided'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Sale::with(['items.product', 'cashier'])->latest();

        if ($search = ltrim(trim((string) $request->query('search')), '#')) {
            $query->where('id', $search);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return view('sales.index', [
            'sales' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function show(Sale $sale): View
    {
        return view('sales.show', ['sale' => $sale->load(['items.product', 'cashier', 'voidedBy'])]);
    }

    /** Printable receipt (opens on its own, without the app menu). */
    public function receipt(Sale $sale): View
    {
        return view('sales.receipt', ['sale' => $sale->load(['items.product', 'cashier'])]);
    }

    /** POST /pos/checkout — called by the POS screen with fetch(). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,gcash,card'],
        ]);

        try {
            $sale = $this->sales->checkout(
                $data['items'],
                $data['amount_paid'],
                $data['payment_method'],
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // Whole transaction rolled back: nothing was saved, no stock was deducted.
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $stocks = Product::query()
            ->whereIn('id', $sale->items->pluck('product_id'))
            ->pluck('stock', 'id');

        return response()->json([
            'ok' => true,
            'sale_id' => $sale->id,
            'total' => (float) $sale->total,
            'change' => (float) $sale->change_amount,
            'stocks' => $stocks,
        ]);
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        try {
            $this->sales->void($sale, $data['reason'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', "Sale #{$sale->id} was voided and the stock was returned.");
    }
}
