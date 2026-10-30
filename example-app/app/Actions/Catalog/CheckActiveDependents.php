<?php

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

final class CheckActiveDependents
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forInventory(Team $store, InventoryItem $item): array
    {
        return array_values(CatalogItem::where('team_id', $store->id)->where('active', true)->whereIn('id', DB::table('recipe_components')->where('inventory_item_id', $item->id)->select('catalog_item_id'))->get(['id', 'name'])->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAddon(Team $store, CatalogItem $addon): array
    {
        return array_values(CatalogItem::where('team_id', $store->id)->where('active', true)->whereIn('id', DB::table('catalog_allowed_addons')->where('addon_id', $addon->id)->select('catalog_item_id'))->get(['id', 'name'])->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all());
    }
}
