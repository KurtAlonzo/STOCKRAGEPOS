<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StockDemand
{
    /** @var array<int, InventoryItem> */
    private array $stocks = [];

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function calculate(Team $store, array $lines): array
    {
        Validator::make(['lines' => $lines], ['lines' => 'array', 'lines.*.id' => 'required|uuid|distinct', 'lines.*.productId' => 'required|integer', 'lines.*.quantity' => 'required|integer|min:1|max:9007199254740', 'lines.*.addonIds' => 'present|array', 'lines.*.addonIds.*' => 'integer'])->validate();
        $demand = [];
        $selectedIds = [];
        foreach ($lines as $line) {
            $selectedIds[] = (int) $line['productId'];
            array_push($selectedIds, ...array_map('intval', $line['addonIds']));
        }
        $items = CatalogItem::with(['stockItem', 'components.inventoryItem', 'addons'])->where('team_id', $store->id)->whereIn('id', array_unique($selectedIds))->get()->keyBy('id');
        foreach ($selectedIds as $id) {
            if (! $items->has($id)) {
                throw (new ModelNotFoundException)->setModel(CatalogItem::class, [$id]);
            }
        }
        foreach ($lines as $i => $line) {
            $product = $items[(int) $line['productId']];
            if (! $product->active || $product->kind === 'addon') {
                throw ValidationException::withMessages(['lines.'.$i.'.productId' => 'Select an active retail or food product.']);
            }
            $this->addSelection($store, $product, (int) $line['quantity'], $demand, 'lines.'.$i.'.productId');
            $ids = array_map('intval', $line['addonIds']);
            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['lines.'.$i.'.addonIds' => 'An add-on cannot repeat.']);
            }
            $allowed = $product->addons->modelKeys();
            foreach ($ids as $id) {
                $addon = $items[$id];
                if (! $addon->active || $addon->kind !== 'addon' || ! in_array($id, $allowed)) {
                    throw ValidationException::withMessages(['lines.'.$i.'.addonIds' => 'Select allowed active add-ons.']);
                }
                $this->addSelection($store, $addon, (int) $line['quantity'], $demand, 'lines.'.$i.'.addonIds');
            }
        }

        return $this->effects($store, $demand);
    }

    /** @param array<int, int> $demand */
    private function addSelection(Team $store, CatalogItem $item, int $count, array &$demand, string $field): void
    {
        try {
            $this->add($store, $item, $count, $demand);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages([$field => array_merge(...array_values($error->errors()))]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forItem(Team $store, CatalogItem $item): array
    {
        abort_unless($item->team_id === $store->id, 404);
        $demand = [];
        $this->add($store, $item, 1, $demand);

        return $this->effects($store, $demand);
    }

    /**
     * @param  array<int, int>  $demand
     */
    private function add(Team $store, CatalogItem $item, int $count, array &$demand): void
    {
        if (! $item->active) {
            throw ValidationException::withMessages(['product' => 'Inactive product.']);
        }
        $item->loadMissing(['stockItem', 'components.inventoryItem']);
        if ($item->stock_item_id !== null) {
            $stock = $item->stockItem;
            abort_unless($stock && $stock->team_id === $store->id, 404);
            $this->stocks[$stock->id] = $stock;
            if (! $stock->active) {
                throw ValidationException::withMessages(['product' => 'Inactive stock record.']);
            }$demand[$stock->id] = Quantity::add($demand[$stock->id] ?? 0, Quantity::multiply(1000, $count));

            return;
        }
        $parts = $item->components;
        if ($parts->isEmpty()) {
            throw ValidationException::withMessages(['components' => 'Recipe has no components.']);
        }
        foreach ($parts as $part) {
            $stock = $part->inventoryItem;
            abort_unless($stock && $stock->team_id === $store->id, 404);
            $this->stocks[$stock->id] = $stock;
            if (! $stock->active || ! in_array($stock->kind, ['ingredient', 'supply'], true) || $stock->unit !== $part->unit) {
                throw ValidationException::withMessages(['components' => 'Recipe needs an active ingredient or supply in its configured unit.']);
            }$demand[$stock->id] = Quantity::add($demand[$stock->id] ?? 0, Quantity::multiply((int) $part->quantity_thousandths, $count));
        }
    }

    /**
     * @param  array<int, int>  $demand
     * @return list<array<string, mixed>>
     */
    private function effects(Team $store, array $demand): array
    {
        ksort($demand);
        $effects = [];
        foreach ($demand as $id => $amount) {
            $stock = $this->stocks[$id];
            $effects[] = ['inventoryItemId' => $id, 'inventoryItemName' => $stock->name, 'unit' => $stock->unit, 'before' => Quantity::format($stock->balance_thousandths), 'demand' => Quantity::format($amount), 'after' => Quantity::format(Quantity::add($stock->balance_thousandths, -$amount)), 'policy' => in_array($stock->kind, ['retail', 'addon'], true) ? 'block' : 'warn'];
        }

        return $effects;
    }
}
