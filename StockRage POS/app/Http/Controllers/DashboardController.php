<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\SqlDate;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now = now();
        $monthRange = [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];

        // Always Sale::completed() so voided sales are never counted.
        $todaySales = (float) Sale::completed()->whereDate('created_at', $now->toDateString())->sum('total');
        $monthlyRevenue = (float) Sale::completed()->whereBetween('created_at', $monthRange)->sum('total');
        $totalOrders = Sale::completed()->whereBetween('created_at', $monthRange)->count();

        // Estimated profit this month = (price - cost at sale time) x qty, completed sales only.
        $monthlyProfit = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $monthRange)
            ->sum(DB::raw('(sale_items.price - sale_items.cost) * sale_items.qty'));

        $lowStock = Product::active()
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->orderBy('stock')->orderBy('name')
            ->get();

        $recent = Sale::completed()->with('items.product')->latest()->take(5)->get();

        // Monthly revenue — last 6 months (gaps filled with 0).
        $monthKey = SqlDate::key('created_at', 'month');
        $monthlyRows = Sale::completed()
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->selectRaw("$monthKey as label, SUM(total) as total")
            ->groupBy('label')->orderBy('label')
            ->pluck('total', 'label');

        $monthly = collect(range(5, 0))->map(function ($ago) use ($now, $monthlyRows) {
            $m = $now->copy()->startOfMonth()->subMonths($ago);

            return ['label' => $m->format('M'), 'total' => (float) ($monthlyRows[$m->format('Y-m')] ?? 0)];
        });

        // Daily trend — last 14 days.
        $dayKey = SqlDate::key('created_at', 'day');
        $dailyRows = Sale::completed()
            ->where('created_at', '>=', $now->copy()->subDays(13)->startOfDay())
            ->selectRaw("$dayKey as label, SUM(total) as total")
            ->groupBy('label')->orderBy('label')
            ->pluck('total', 'label');

        $daily = collect(range(13, 0))->map(function ($ago) use ($now, $dailyRows) {
            $d = $now->copy()->subDays($ago);

            return ['label' => $d->format('M j'), 'total' => (float) ($dailyRows[$d->format('Y-m-d')] ?? 0)];
        });

        // Best sellers — completed sales only, top 5 by quantity.
        $best = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', Sale::COMPLETED)
            ->groupBy('sale_items.product_id', 'products.name')
            ->select('products.name', DB::raw('SUM(sale_items.qty) as qty'))
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['label' => $r->name, 'total' => (int) $r->qty]);

        return view('dashboard', compact(
            'todaySales', 'monthlyRevenue', 'monthlyProfit', 'totalOrders', 'lowStock', 'recent', 'monthly', 'daily', 'best',
        ));
    }
}
