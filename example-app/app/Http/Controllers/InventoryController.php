<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\SaveInventoryItem;
use App\Data\InventoryData;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, string $current_team): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $filters = $request->validate(['query' => 'nullable|string|max:255', 'kind' => 'nullable|in:retail,ingredient,supply,addon', 'stockState' => 'nullable|in:positive,zero,negative,low', 'active' => 'nullable|in:0,1', 'itemId' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'movementPage' => 'nullable|integer|min:1']);
        $query = InventoryItem::where('team_id', $store->id);
        if ($filters['query'] ?? null) {
            $query->where('name', 'like', '%'.$filters['query'].'%');
        }
        foreach (['kind', 'active'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        match ($filters['stockState'] ?? null) {
            'negative' => $query->where('balance_thousandths', '<', 0),
            'zero' => $query->where('balance_thousandths', 0),
            'low' => $query->where('balance_thousandths', '>', 0)->whereColumn('balance_thousandths', '<=', 'threshold_thousandths'),
            'positive' => $query->where('balance_thousandths', '>', 0)->where(fn ($q) => $q->whereNull('threshold_thousandths')->orWhereColumn('balance_thousandths', '>', 'threshold_thousandths')),
            default => null,
        };
        $page = $query->orderBy('name')->orderBy('id')->paginate(25)->through(fn ($item) => InventoryData::item($item));
        $selected = isset($filters['itemId']) ? InventoryItem::where('team_id', $store->id)->whereKey($filters['itemId'])->firstOrFail() : null;
        $movements = $selected ? InventoryData::page(StockMovement::where('team_id', $store->id)->where('inventory_item_id', $selected->id)->orderByDesc('id')->paginate(10, ['*'], 'movementPage')->through(fn ($movement) => InventoryData::movement($movement))) : null;

        return Inertia::render('inventory/index', ['business' => InventoryData::business($store), 'inventory' => InventoryData::page($page), 'filters' => ['query' => $filters['query'] ?? '', 'kind' => $filters['kind'] ?? null, 'stockState' => $filters['stockState'] ?? null, 'active' => isset($filters['active']) ? (bool) $filters['active'] : null], 'selectedItem' => $selected ? InventoryData::item($selected) : null, 'movements' => $movements]);
    }

    public function store(Request $request, string $current_team, SaveInventoryItem $save): RedirectResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $save->handle($store, $request->user(), $request->all());

        return to_route('inventory.index', ['current_team' => $store->slug]);
    }

    public function update(Request $request, string $current_team, string $inventoryItem, SaveInventoryItem $save): RedirectResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $item = InventoryItem::where('team_id', $store->id)->whereKey($inventoryItem)->firstOrFail();
        $save->handle($store, $request->user(), $request->all(), $item);

        return to_route('inventory.index', ['current_team' => $store->slug, 'itemId' => $item->id]);
    }
}
