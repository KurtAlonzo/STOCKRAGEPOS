<?php

namespace App\Data;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\Team;
use App\Support\Quantity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InventoryData
{
    /**
     * @return array<string, mixed>
     */
    public static function item(InventoryItem $item): array
    {
        $state = $item->balance_thousandths < 0 ? 'negative' : ($item->balance_thousandths === 0 ? 'zero' : ($item->threshold_thousandths !== null && $item->balance_thousandths <= $item->threshold_thousandths ? 'low' : 'positive'));

        return ['id' => $item->id, 'name' => $item->name, 'kind' => $item->kind, 'unit' => $item->unit, 'active' => $item->active, 'balance' => Quantity::format($item->balance_thousandths), 'revision' => (string) $item->revision, 'shortagePolicy' => in_array($item->kind, ['retail', 'addon'], true) ? 'block' : 'warn', 'lowStockThreshold' => $item->threshold_thousandths === null ? null : Quantity::format($item->threshold_thousandths), 'stockState' => $state];
    }

    /**
     * @return array<string, mixed>
     */
    public static function business(Team $store): array
    {
        return ['storeId' => $store->id, 'storeSlug' => $store->slug, 'currency' => 'PHP', 'timezone' => 'Asia/Manila', 'taxTreatment' => 'unconfigured'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function movement(StockMovement $movement): array
    {
        return ['id' => $movement->id, 'inventoryItemId' => $movement->inventory_item_id, 'kind' => $movement->kind, 'before' => Quantity::format((int) $movement->before_thousandths), 'delta' => Quantity::format((int) $movement->delta_thousandths), 'after' => Quantity::format((int) $movement->after_thousandths), 'unit' => $movement->unit, 'reason' => $movement->reason, 'actorId' => $movement->actor_id, 'actorName' => $movement->actor_name, 'itemName' => $movement->item_name, 'occurredAt' => $movement->created_at->toISOString(), 'sourceType' => $movement->kind, 'sourceId' => StockOperation::findOrFail($movement->stock_operation_id)->sale_id ?? $movement->stock_operation_id];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array<string, mixed>
     */
    public static function page(LengthAwarePaginator $page): array
    {
        return ['data' => $page->items(), 'currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()];
    }
}
