<?php

namespace App\Services\Pos;

use App\Models\CheckoutAttempt;
use App\Models\Team;
use App\Models\User;

final class CheckoutAttempts
{
    /** @param array<string, mixed> $input */
    public function register(Team $store, User $actor, array $input): CheckoutAttempt
    {
        $canonical = CheckoutRequest::canonical($input);
        $hash = CheckoutRequest::hash($canonical);
        // createOrFirst uses a savepoint and the unique index to resolve competing registrations.
        $attempt = CheckoutAttempt::firstOrCreate(['team_id' => $store->id, 'actor_id' => $actor->id, 'idempotency_key' => $canonical['idempotencyKey']], ['request_json' => $canonical, 'request_hash' => $hash, 'status' => 'pending']);
        if (! hash_equals($attempt->request_hash, $hash)) {
            throw new IdempotencyConflict('This operation key already belongs to a different request.');
        }

        return $attempt;
    }

    /** @return array<string, mixed> */
    public function lookup(Team $store, User $actor, string $key): array
    {
        $attempt = CheckoutAttempt::where('team_id', $store->id)->where('actor_id', $actor->id)->where('idempotency_key', $key)->first();
        if (! $attempt) {
            return ['status' => 'not_found'];
        }
        if ($attempt->status === 'pending') {
            return ['status' => 'pending', 'idempotencyKey' => $key];
        }
        $body = $attempt->result_json['body'];

        return $attempt->status === 'confirmed' ? $body : ['status' => 'rejected', 'idempotencyKey' => $key, 'error' => $body['error']];
    }
}
