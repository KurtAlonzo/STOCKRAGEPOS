<?php

namespace App\Services\Pos;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class QuoteStore
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $quote
     */
    public function put(Team $store, User $actor, array $input, array $quote): void
    {
        try {
            $stored = Cache::add($this->key($store, $actor, $quote['id']), ['input' => $input, 'quote' => $quote], 300);
        } catch (\Throwable $error) {
            throw new QuoteUnavailable('Unable to store quote.', previous: $error);
        }
        if (! $stored) {
            throw new QuoteUnavailable('Unable to store quote. Retry with current cart.');
        }
    }

    /** @return array<string, mixed>|null */
    public function get(Team $store, User $actor, string $quoteId): ?array
    {
        return Cache::get($this->key($store, $actor, $quoteId));
    }

    private function key(Team $store, User $actor, string $quoteId): string
    {
        return 'pos:quote:'.$store->id.':'.$actor->id.':'.$quoteId;
    }
}
