<?php

namespace App\Data;

use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockOperation;

final class SaleData
{
    /** @return array<string, mixed> */
    public static function make(Sale $sale): array
    {
        $sale->loadMissing(['payment', 'reversal.attempt']);
        $payment = $sale->payment;
        $movements = StockMovement::with('operation')->whereIn('stock_operation_id', StockOperation::where('sale_id', $sale->id)->select('id'))->orderBy('id')->get();

        return ['id' => $sale->id, 'reference' => $sale->reference, 'storeId' => $sale->team_id, 'actorId' => $sale->actor_id, 'status' => $sale->status, 'occurredAt' => $sale->occurred_at->toISOString(), 'currency' => $sale->currency, 'subtotalMinor' => $sale->subtotal_minor, 'totalMinor' => $sale->total_minor, 'lines' => $sale->lines_json, 'payment' => ['id' => $payment->id, 'receivedMinor' => $payment->received_minor, 'changeMinor' => $payment->change_minor, 'recordedAt' => $payment->recorded_at->toISOString(), 'input' => $payment->input_json], 'stockMovements' => $movements->map(fn ($movement) => InventoryData::movement($movement))->all(), 'reversal' => $sale->reversal ? SaleReversalData::make($sale->reversal) : null];
    }
}
