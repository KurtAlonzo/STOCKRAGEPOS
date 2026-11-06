<?php

namespace App\Data;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Team;
use App\Services\Catalog\StockDemand;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;

final class CatalogData
{
    /**
     * @return array<string, mixed>
     */
    public static function category(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'active' => $category->active, 'revision' => (string) $category->revision];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(CatalogItem $item): array
    {
        $data = ['id' => $item->id, 'name' => $item->name, 'sku' => $item->sku, 'kind' => $item->kind, 'categoryId' => $item->category_id, 'active' => $item->active, 'priceMinor' => $item->price_minor, 'revision' => (string) $item->revision, 'allowedAddonIds' => $item->addons->modelKeys()];
        if ($item->kind === 'addon') {
            $data['stockBehavior'] = $item->stock_behavior;
        }
        if ($item->stock_item_id !== null) {
            $data['stockItemId'] = $item->stock_item_id;
        } else {
            $data['recipeRevision'] = (string) $item->recipe_revision;
            $data['components'] = $item->components->map(fn ($part) => ['inventoryItemId' => $part->inventory_item_id, 'quantity' => Quantity::format((int) $part->quantity_thousandths), 'unit' => $part->unit])->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function availability(CatalogItem $item, ?Team $store = null): array
    {
        $item->loadMissing(['stockItem', 'components.inventoryItem']);
        $stock = $item->stockItem;
        if (! $item->active) {
            return ['status' => 'inactive', 'stock' => $stock ? InventoryData::item($stock) : null, 'issues' => []];
        }
        $issues = [];
        $status = 'available';
        try {
            $store ??= Team::findOrFail($item->team_id);
            $effects = (new StockDemand)->forItem($store, $item);
            foreach ($effects as $effect) {
                $balance = Quantity::parse($effect['after'], $effect['unit'], true);
                if ($balance < 0) {
                    $blocked = $effect['policy'] === 'block';
                    $status = $blocked ? 'blocked' : ($status === 'blocked' ? 'blocked' : 'warning');
                    $component = $stock ?? $item->components->firstWhere('inventory_item_id', $effect['inventoryItemId'])?->inventoryItem;
                    if (! $component) {
                        continue;
                    }
                    $issues[] = ['id' => 'stock-'.$component->id, 'code' => $blocked ? 'direct_stock_shortage' : 'recipe_stock_warning', 'message' => $component->name.': projected '.$effect['after'].' '.$effect['unit'].' per unit', 'field' => null, 'inventoryItemId' => $component->id];
                }
            }
        } catch (ValidationException $error) {
            $status = 'blocked';
            $issues[] = ['id' => 'configuration', 'code' => 'invalid_configuration', 'message' => 'Repair missing or inactive recipe components.', 'field' => null, 'inventoryItemId' => null];
        }
        if ($stock && $status === 'available' && $stock->threshold_thousandths !== null && $stock->balance_thousandths <= $stock->threshold_thousandths) {
            $status = 'warning';
        }

        return ['status' => $status, 'stock' => $stock ? InventoryData::item($stock) : null, 'issues' => $issues];
    }
}
