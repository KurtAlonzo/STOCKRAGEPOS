<?php

namespace App\Data;

use App\Models\SaleReversal;

final class SaleReversalData
{
    /** @return array<string, mixed> */
    public static function make(SaleReversal $reversal): array
    {
        return ['id' => $reversal->id, 'saleId' => $reversal->sale_id, 'idempotencyKey' => $reversal->attempt->idempotency_key, 'reason' => $reversal->reason, 'refundConfirmed' => $reversal->refund_confirmed, 'refundMethod' => $reversal->refund_method, 'refundReference' => $reversal->refund_reference, 'restock' => $reversal->request_json['restock'], 'totalMinor' => $reversal->total_minor, 'actorId' => $reversal->actor_id, 'actorName' => $reversal->actor_name, 'occurredAt' => $reversal->occurred_at->toISOString()];
    }
}
