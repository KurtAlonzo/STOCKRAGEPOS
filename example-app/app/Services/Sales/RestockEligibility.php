<?php

namespace App\Services\Sales;

use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Support\Quantity;

final class RestockEligibility
{
    /** @return list<array<string, mixed>> */
    public function forSale(Sale $sale): array
    {
        $direct = [];
        foreach ($sale->lines_json as $line) {
            foreach ([$line, ...($line['addons'] ?? [])] as $part) {
                $id = $part['directStockItemId'] ?? null;
                if (! $id || ! empty($part['components'])) {
                    continue;
                }
                $direct[$id] = Quantity::add($direct[$id] ?? 0, Quantity::multiply(1000, $line['quantity']));
            }
        }
        $movements = StockMovement::where('team_id', $sale->team_id)->where('kind', 'sale')->whereIn('stock_operation_id', StockOperation::where('team_id', $sale->team_id)->where('sale_id', $sale->id)->where('kind', 'sale')->select('id'))->whereIn('inventory_item_id', array_keys($direct))->orderBy('id')->get()->groupBy('inventory_item_id');
        $stocks = InventoryItem::where('team_id', $sale->team_id)->whereIn('id', array_keys($direct))->orderBy('id')->get();
        $result = [];
        foreach ($stocks as $stock) {
            $history = $movements->get($stock->id);
            if (! $history || $stock->unit !== 'each' || ! in_array($stock->kind, ['retail', 'addon'], true)) {
                continue;
            }
            $deducted = 0;
            foreach ($history as $movement) {
                if ($movement->unit !== 'each' || $movement->delta_thousandths >= 0) {
                    continue;
                }
                $deducted = Quantity::add($deducted, -(int) $movement->delta_thousandths);
            }
            $maximum = min($direct[$stock->id], $deducted);
            if ($maximum <= 0 || $maximum % 1000 !== 0) {
                continue;
            }
            $result[] = ['inventoryItemId' => $stock->id, 'name' => $history->first()->item_name, 'unit' => 'each', 'maxQuantity' => Quantity::format($maximum), 'active' => $stock->active];
        }

        return $result;
    }
}
