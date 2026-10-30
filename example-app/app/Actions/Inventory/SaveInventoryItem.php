<?php

namespace App\Actions\Inventory;

use App\Actions\Catalog\CheckActiveDependents;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveInventoryItem
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Team $store, User $actor, array $input, ?InventoryItem $item = null): InventoryItem
    {
        return StoreCatalogTransaction::run($store, function () use ($store, $input, $item) {
            $current = $item ? InventoryItem::where('team_id', $store->id)->whereKey($item->id)->firstOrFail() : null;
            $data = Validator::make($input, ['name' => 'required|string|max:255', 'active' => 'required|boolean', 'lowStockThreshold' => 'present|nullable|string', 'kind' => $current ? 'prohibited' : 'required|in:ingredient,supply', 'unit' => $current ? 'prohibited' : 'required|in:each,g,ml', 'expectedRevision' => $current ? 'required|string' : 'prohibited', 'balance' => 'prohibited', 'balance_thousandths' => 'prohibited'])->validate();
            $name = trim($data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Enter a name.']);
            }
            $unit = $current->unit ?? $data['unit'];
            try {
                $threshold = $data['lowStockThreshold'] === null ? null : Quantity::parse($data['lowStockThreshold'], $unit);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['lowStockThreshold' => array_values($e->errors())[0]]);
            }
            if ($current) {
                if ((string) $current->revision !== $data['expectedRevision']) {
                    throw ValidationException::withMessages(['expectedRevision' => 'This item changed. Reload before saving.']);
                }
                if (! $data['active']) {
                    $dependents = (new CheckActiveDependents)->forInventory($store, $current);
                    if ($dependents) {
                        throw ValidationException::withMessages(['active' => 'Used by active products: '.implode(', ', array_column($dependents, 'name'))]);
                    }
                }
                if (in_array($current->kind, ['retail', 'addon'], true) && ($current->name !== $name || $current->active !== (bool) $data['active'])) {
                    throw ValidationException::withMessages(['name' => 'Edit the name and active state through its catalog product.']);
                }
                $current->update(['name' => $name, 'active' => $data['active'], 'threshold_thousandths' => $threshold, 'revision' => $current->revision + 1]);

                return $current;
            }

            return InventoryItem::create(['team_id' => $store->id, 'name' => $name, 'kind' => $data['kind'], 'unit' => $unit, 'active' => $data['active'], 'threshold_thousandths' => $threshold]);
        });
    }
}
