<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\Team;
use App\Models\User;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Validation\ValidationException;

final class RecordStockOperation
{
    public function handle(Team $store, User $actor, InventoryItem $item, string $kind, string $quantityOrDelta, string $reason, string $expectedRevision): StockMovement
    {
        return StoreCatalogTransaction::run($store, function () use ($store, $actor, $item, $kind, $quantityOrDelta, $reason, $expectedRevision) {
            $current = InventoryItem::where('team_id', $store->id)->findOrFail($item->id);
            if ((string) $current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['expectedRevision' => 'This item changed. Reload before saving.']);
            }
            $field = $kind === 'receipt' ? 'quantity' : 'delta';
            if (! in_array($kind, ['receipt', 'adjustment'], true) || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Enter a reason.']);
            }
            try {
                $delta = Quantity::parse($quantityOrDelta, $current->unit, $kind === 'adjustment');
                $after = Quantity::add($current->balance_thousandths, $delta);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([$field => array_values($e->errors())[0]]);
            }
            if ($delta === 0 || ($kind === 'receipt' && $delta < 0) || ($after < 0 && in_array($current->kind, ['retail', 'addon'], true))) {
                throw ValidationException::withMessages([$field => 'Enter a nonzero quantity. Direct stock cannot become negative.']);
            }
            $updated = InventoryItem::where('team_id', $store->id)->whereKey($current->id)->where('revision', $current->revision)->update(['balance_thousandths' => $after, 'revision' => $current->revision + 1, 'updated_at' => now()]);
            if (! $updated) {
                throw ValidationException::withMessages(['expectedRevision' => 'This item changed. Reload before saving.']);
            }
            $operation = StockOperation::create(['team_id' => $store->id, 'kind' => $kind, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => trim($reason)]);

            return StockMovement::create(['team_id' => $store->id, 'inventory_item_id' => $current->id, 'stock_operation_id' => $operation->id, 'kind' => $kind, 'unit' => $current->unit, 'item_name' => $current->name, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => trim($reason), 'before_thousandths' => $current->balance_thousandths, 'delta_thousandths' => $delta, 'after_thousandths' => $after]);
        });
    }
}
