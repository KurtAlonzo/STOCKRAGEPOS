<?php

namespace App\Data;

use App\Models\Sale;

final class SalesData
{
    /** @return array<string, mixed> */
    public static function summary(Sale $sale): array
    {
        $refunded = $sale->reversal->total_minor ?? 0;

        return ['id' => $sale->id, 'reference' => $sale->reference, 'status' => $sale->status, 'occurredAt' => $sale->occurred_at->toISOString(), 'totalMinor' => $sale->total_minor, 'paymentMethod' => $sale->payment->method, 'refundedMinor' => $refunded, 'netMinor' => $sale->total_minor - $refunded];
    }
}
