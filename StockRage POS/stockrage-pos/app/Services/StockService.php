<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The ONLY place in the app that changes product stock.
 * Every change writes a StockMovement row, so Stock History is always correct.
 */
class StockService
{
    /**
     * @param  int  $qty  positive to add stock, negative to remove it
     * @param  string  $type  one of StockMovement::TYPES (in, sale, adjustment, void)
     */
    public function change(
        Product $product,
        int $qty,
        string $type,
        ?string $reason = null,
        ?CarbonInterface $at = null,
    ): StockMovement {
        if (! in_array($type, StockMovement::TYPES, true)) {
            throw new InvalidArgumentException("Unknown stock movement type: {$type}");
        }

        return DB::transaction(function () use ($product, $qty, $type, $reason, $at) {
            // Re-read the row with a lock so two cashiers can never oversell.
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            $previous = $locked->stock;
            $new = $previous + $qty;

            if ($new < 0) {
                throw new RuntimeException(
                    "Not enough stock for {$locked->name} (only {$previous} left)"
                );
            }

            $locked->update(['stock' => $new]);
            $product->setRawAttributes($locked->getAttributes(), true);

            $movement = new StockMovement([
                'product_id' => $locked->id,
                'type' => $type,
                'qty_change' => $qty,
                'previous_qty' => $previous,
                'new_qty' => $new,
                'reason' => $reason,
            ]);

            if ($at) {
                $movement->created_at = $at;
                $movement->updated_at = $at;
            }

            $movement->save();

            return $movement;
        });
    }
}
