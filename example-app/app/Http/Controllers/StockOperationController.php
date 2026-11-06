<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\RecordStockOperation;
use App\Models\InventoryItem;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockOperationController extends Controller
{
    public function receipt(Request $request, string $current_team, RecordStockOperation $action): RedirectResponse
    {
        return $this->record($request, $current_team, $action, 'receipt');
    }

    public function adjustment(Request $request, string $current_team, RecordStockOperation $action): RedirectResponse
    {
        return $this->record($request, $current_team, $action, 'adjustment');
    }

    private function record(Request $request, string $slug, RecordStockOperation $action, string $kind): RedirectResponse
    {
        $store = Team::where('slug', $slug)->firstOrFail();
        $request->validate(['inventoryItemId' => 'required|integer']);
        $item = InventoryItem::where('team_id', $store->id)->whereKey($request->input('inventoryItemId'))->firstOrFail();
        $field = $kind === 'receipt' ? 'quantity' : 'delta';
        $data = $request->validate([$field => 'required|string', 'reason' => 'required|string|max:1000', 'expectedRevision' => 'required|string']);
        $action->handle($store, $request->user(), $item, $kind, $data[$field], $data['reason'], $data['expectedRevision']);

        return to_route('inventory.index', ['current_team' => $slug, 'itemId' => $item->id]);
    }
}
