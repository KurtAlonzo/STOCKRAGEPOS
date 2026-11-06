<?php

namespace App\Actions\Pos;

use App\Models\Team;
use App\Models\User;
use App\Services\Pos\QuoteCalculator;
use App\Services\Pos\QuoteStore;
use App\Support\StoreCatalogTransaction;

final class CreateQuote
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function handle(Team $store, User $actor, array $validated): array
    {
        return StoreCatalogTransaction::run($store, function () use ($store, $actor, $validated) {
            $quote = (new QuoteCalculator)->calculate($store, $validated);
            $lines = array_map(function ($line) {
                sort($line['addonIds'], SORT_NUMERIC);

                return ['id' => $line['id'], 'productId' => (int) $line['productId'], 'quantity' => (int) $line['quantity'], 'addonIds' => array_map('intval', $line['addonIds'])];
            }, $validated['lines']);
            app(QuoteStore::class)->put($store, $actor, ['lines' => $lines, 'payment' => $quote['payment']], $quote);

            return $quote;
        });
    }
}
