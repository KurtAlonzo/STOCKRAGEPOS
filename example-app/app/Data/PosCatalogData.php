<?php

namespace App\Data;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Team;

final class PosCatalogData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function page(Team $store, array $filters): array
    {
        if (isset($filters['categoryId'])) {
            Category::where('team_id', $store->id)->findOrFail($filters['categoryId']);
        }
        $base = CatalogItem::where('team_id', $store->id)->where('active', true)->whereIn('kind', ['retail', 'food']);
        $hasProducts = (clone $base)->exists();
        if ($filters['query'] ?? '') {
            $base->where(fn ($q) => $q->where('name', 'like', '%'.$filters['query'].'%')->orWhere('sku', 'like', '%'.$filters['query'].'%'));
        }
        if (isset($filters['categoryId'])) {
            $base->where('category_id', $filters['categoryId']);
        }
        $page = $base->with(['stockItem', 'components.inventoryItem', 'addons.stockItem', 'addons.components.inventoryItem', 'addons.addons'])->orderBy('name')->orderBy('id')->paginate(25, ['*'], 'page', $filters['page'] ?? 1);
        $addons = collect();
        $inventory = collect();
        foreach ($page->items() as $item) {
            foreach ([$item, ...$item->addons] as $selected) {
                abort_unless($selected->team_id === $store->id, 404);
                if ($selected->kind === 'addon') {
                    $addons->put($selected->id, $selected);
                }
                foreach ([$selected->stockItem, ...$selected->components->pluck('inventoryItem')] as $stock) {
                    if ($stock) {
                        abort_unless($stock->team_id === $store->id, 404);
                        $inventory->put($stock->id, $stock);
                    }
                }
            }
        }
        $serialize = fn ($item) => [...CatalogData::item($item), 'availability' => CatalogData::availability($item, $store)];
        $page->through($serialize);

        return ['catalog' => InventoryData::page($page), 'addons' => $addons->values()->map($serialize)->all(), 'inventory' => $inventory->values()->map(fn ($stock) => InventoryData::item($stock))->all(), 'filters' => ['query' => $filters['query'] ?? '', 'categoryId' => isset($filters['categoryId']) ? (int) $filters['categoryId'] : null], 'hasProducts' => $hasProducts];
    }
}
