<?php

namespace App\Data;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\Team;

final class PosDraftCatalogData
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public static function resolve(Team $store, array $lines): array
    {
        $ids = [];
        foreach ($lines as $line) {
            array_push($ids, $line['productId'], ...$line['addonIds']);
        }
        $items = CatalogItem::with(['addons', 'components'])->where('team_id', $store->id)->whereIn('id', $ids)->get()->keyBy('id');
        $issues = [];
        $inventoryIds = [];
        foreach ($lines as $line) {
            $product = $items->get($line['productId']);
            $valid = $product && $product->active && $product->kind !== 'addon';
            foreach ($line['addonIds'] as $id) {
                $addon = $items->get($id);
                $valid = $valid && $addon && $addon->active && $addon->kind === 'addon' && $product->addons->contains('id', $id);
            }
            if (! $valid) {
                $issues[] = ['id' => 'draft-'.$line['id'], 'code' => 'unavailable_selection', 'message' => 'A saved selection is unavailable. Correct or remove this line.', 'field' => $line['id'], 'inventoryItemId' => null];
            }
        }
        $data = $items->map(function ($item) use ($store, &$inventoryIds) {
            if ($item->stock_item_id) {
                $inventoryIds[] = $item->stock_item_id;
            }
            array_push($inventoryIds, ...$item->components->pluck('inventory_item_id')->all());

            return [...CatalogData::item($item), 'availability' => CatalogData::availability($item, $store)];
        });

        return ['products' => $data->filter(fn ($item) => $item['kind'] !== 'addon')->values()->all(), 'addons' => $data->filter(fn ($item) => $item['kind'] === 'addon')->values()->all(), 'inventory' => InventoryItem::where('team_id', $store->id)->whereIn('id', $inventoryIds)->get()->map(fn ($item) => InventoryData::item($item))->all(), 'issues' => $issues];
    }
}
