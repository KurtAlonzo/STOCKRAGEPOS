<?php

namespace App\Services\Sales;

use App\Models\ReversalAttempt;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Services\Pos\IdempotencyConflict;

final class ReversalAttempts
{
    /** @param array<string, mixed> $canonical */
    public function register(Team $store, User $actor, Sale $sale, array $canonical): ReversalAttempt
    {
        $hash = ReversalRequest::hash($canonical);
        $attempt = ReversalAttempt::firstOrCreate(['team_id' => $store->id, 'actor_id' => $actor->id, 'idempotency_key' => $canonical['idempotencyKey']], ['sale_id' => $sale->id, 'request_json' => $canonical, 'request_hash' => $hash, 'status' => 'pending']);
        if (! hash_equals($attempt->request_hash, $hash)) {
            throw new IdempotencyConflict('This operation key belongs to a different refund request.');
        }

        return $attempt;
    }

    /** @return array<string, mixed> */
    public function lookup(Team $store, User $actor, Sale $sale, string $key): array
    {
        $identity = ['saleId' => $sale->id, 'idempotencyKey' => $key];
        $attempt = ReversalAttempt::where('team_id', $store->id)->where('actor_id', $actor->id)->where('sale_id', $sale->id)->where('idempotency_key', $key)->first();
        if (! $attempt) {
            return [...$identity, 'status' => 'not_found'];
        }
        if ($attempt->status === 'pending') {
            return [...$identity, 'status' => 'pending'];
        }

        return $attempt->status === 'recorded' ? $attempt->result_json['body'] : [...$identity, 'status' => 'rejected', 'error' => $attempt->result_json['body']['error']];
    }
}
