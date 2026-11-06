<?php

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveCatalogItem
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Team $store, User $actor, array $input, ?CatalogItem $item = null): CatalogItem
    {
        return StoreCatalogTransaction::run($store, function () use ($store, $input, $item) {
            $current = $item ? CatalogItem::where('team_id', $store->id)->whereKey($item->id)->firstOrFail() : null;
            $direct = ($input['kind'] ?? null) === 'retail' || (($input['kind'] ?? null) === 'addon' && ($input['stockBehavior'] ?? null) === 'direct');
            $data = Validator::make($input, ['name' => 'required|string|max:255', 'sku' => 'required|string|max:64', 'kind' => 'required|in:retail,food,addon', 'stockBehavior' => ($input['kind'] ?? null) === 'addon' ? 'required|in:direct,recipe' : 'prohibited', 'components' => $direct ? 'prohibited' : 'present|array', 'categoryId' => 'present|nullable|integer', 'active' => 'required|boolean', 'priceMinor' => 'required|integer|min:0|max:9007199254740991', 'allowedAddonIds' => 'present|array', 'allowedAddonIds.*' => 'integer', 'stockItemId' => 'prohibited', 'lowStockThreshold' => ! $current && $direct ? 'present|nullable|string' : 'prohibited', 'expectedRevision' => $current ? 'required|string' : 'prohibited'])->validate();
            $name = trim($data['name']);
            $sku = strtoupper(trim($data['sku']));
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Enter a product name.']);
            }
            if (! preg_match('/^[A-Z0-9._-]{1,64}$/D', $sku)) {
                throw ValidationException::withMessages(['sku' => 'Use letters, digits, dash, underscore or dot.']);
            }
            if ($current && (string) $current->revision !== $data['expectedRevision']) {
                throw ValidationException::withMessages(['expectedRevision' => 'This item changed. Reload before saving.']);
            }
            if ($current && ($current->kind !== $data['kind'] || $current->stock_behavior !== ($data['stockBehavior'] ?? null))) {
                throw ValidationException::withMessages(['kind' => 'Product type and stock behavior cannot change.']);
            }
            if ($current && $current->kind === 'addon' && ! $data['active']) {
                $dependents = (new CheckActiveDependents)->forAddon($store, $current);
                if ($dependents) {
                    throw ValidationException::withMessages(['active' => 'Used by active products: '.implode(', ', array_column($dependents, 'name'))]);
                }
            }
            $addonIds = array_map('intval', $data['allowedAddonIds']);
            sort($addonIds);
            if (count($addonIds) !== count(array_unique($addonIds)) || ($data['kind'] === 'addon' && count($addonIds))) {
                throw ValidationException::withMessages(['allowedAddonIds' => 'Add-ons cannot own add-ons; selections cannot repeat.']);
            }
            $existing = $current ? DB::table('catalog_allowed_addons')->where('catalog_item_id', $current->id)->pluck('addon_id')->all() : [];
            foreach ($addonIds as $addonId) {
                $addon = CatalogItem::where('team_id', $store->id)->whereKey($addonId)->firstOrFail();
                if ($addon->kind !== 'addon' || (! $addon->active && ($data['active'] || ! in_array($addonId, $existing)))) {
                    throw ValidationException::withMessages(['allowedAddonIds' => 'Select active add-ons from this store.']);
                }
            }
            if (CatalogItem::where('team_id', $store->id)->where('sku', $sku)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                throw ValidationException::withMessages(['sku' => 'This SKU already exists in this store.']);
            }
            if ($data['categoryId'] !== null) {
                $category = Category::where('team_id', $store->id)->whereKey($data['categoryId'])->firstOrFail();
                if (! $category->active && ($data['active'] || $current?->category_id !== $category->id)) {
                    throw ValidationException::withMessages(['categoryId' => 'Select an active category.']);
                }
            }
            $values = ['name' => $name, 'sku' => $sku, 'kind' => $data['kind'], 'stock_behavior' => $data['stockBehavior'] ?? null, 'category_id' => $data['categoryId'], 'active' => $data['active'], 'price_minor' => $data['priceMinor']];
            if ($current) {
                $current->update([...$values, 'revision' => $current->revision + 1]);
                if ($direct) {
                    InventoryItem::where('team_id', $store->id)->whereKey($current->stock_item_id)->update(['name' => $name, 'active' => $data['active'], 'revision' => DB::raw('revision + 1')]);
                }$saved = $current;
            } else {
                $stock = null;
                if ($direct) {
                    try {
                        $threshold = $data['lowStockThreshold'] === null ? null : Quantity::parse($data['lowStockThreshold'], 'each');
                    } catch (ValidationException $e) {
                        throw ValidationException::withMessages(['lowStockThreshold' => array_values($e->errors())[0]]);
                    }$stock = InventoryItem::create(['team_id' => $store->id, 'name' => $name, 'kind' => $data['kind'], 'unit' => 'each', 'active' => $data['active'], 'threshold_thousandths' => $threshold]);
                }
                $saved = CatalogItem::create([...$values, 'team_id' => $store->id, 'stock_item_id' => $stock?->id]);
            }
            if (! $direct) {
                (new SaveRecipe)->handle($store, $saved, $data['components']);
            }
            DB::table('catalog_allowed_addons')->where('catalog_item_id', $saved->id)->delete();
            foreach ($addonIds as $addonId) {
                DB::table('catalog_allowed_addons')->insert(['catalog_item_id' => $saved->id, 'addon_id' => $addonId]);
            }

            return $saved;
        });
    }
}
