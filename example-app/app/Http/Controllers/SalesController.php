<?php

namespace App\Http\Controllers;

use App\Data\InventoryData;
use App\Data\SaleData;
use App\Data\SalesData;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Sales\RestockEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $data = $request->validate(['reference' => 'nullable|string|max:255', 'dateFrom' => 'nullable|date_format:Y-m-d', 'dateTo' => 'nullable|date_format:Y-m-d|after_or_equal:dateFrom', 'payment' => 'nullable|in:cash,gcash,maya', 'status' => 'nullable|in:completed,reversed', 'page' => 'nullable|integer|min:1']);

        return ['reference' => $data['reference'] ?? '', 'dateFrom' => $data['dateFrom'] ?? null, 'dateTo' => $data['dateTo'] ?? null, 'payment' => $data['payment'] ?? null, 'status' => $data['status'] ?? null];
    }

    public function index(Request $request, string $current_team): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $filters = $this->filters($request);
        $query = Sale::where('team_id', $store->id)->with(['payment', 'reversal']);
        if ($filters['reference'] !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['reference']);
            $query->whereRaw("reference LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        if ($filters['dateFrom']) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['dateFrom'], 'Asia/Manila')->startOfDay()->utc());
        }
        if ($filters['dateTo']) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($filters['dateTo'], 'Asia/Manila')->startOfDay()->addDay()->utc());
        }
        if ($filters['payment']) {
            $query->whereHas('payment', fn ($q) => $q->where('method', $filters['payment']));
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }
        $page = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25)->through(fn ($sale) => SalesData::summary($sale));

        return Inertia::render('sales/index', ['business' => InventoryData::business($store), 'sales' => InventoryData::page($page), 'filters' => $filters]);
    }

    public function show(Request $request, string $current_team, string $sale): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $record = Sale::where('team_id', $store->id)->findOrFail($sale);
        $filters = $this->filters($request);

        return Inertia::render('sales/show', ['business' => InventoryData::business($store), 'sale' => SaleData::make($record), 'restockCandidates' => (new RestockEligibility)->forSale($record), 'storeName' => $store->name, 'returnTo' => ['filters' => $filters, 'page' => (int) $request->input('page', 1)]]);
    }
}
