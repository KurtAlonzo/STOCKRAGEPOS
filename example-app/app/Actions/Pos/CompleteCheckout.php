<?php

namespace App\Actions\Pos;

use App\Data\SaleData;
use App\Models\CheckoutAttempt;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\Team;
use App\Models\User;
use App\Services\Pos\CheckoutAttempts;
use App\Services\Pos\CheckoutRequest;
use App\Services\Pos\IdempotencyConflict;
use App\Services\Pos\QuoteCalculator;
use App\Services\Pos\QuoteReview;
use App\Services\Pos\QuoteStore;
use App\Services\Pos\QuoteUnavailable;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CompleteCheckout
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(Team $store, User $actor, array $input): array
    {
        $input = CheckoutRequest::canonical($input);
        try {
            $attempt = (new CheckoutAttempts)->register($store, $actor, $input);
        } catch (IdempotencyConflict $error) {
            return $this->error('idempotency_conflict', $error->getMessage());
        }
        if ($attempt->status !== 'pending') {
            return $attempt->result_json;
        }
        try {
            return StoreCatalogTransaction::run($store, function () use ($store, $actor, $attempt, $input) {
                $attempt = CheckoutAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($attempt->status !== 'pending') {
                    return $attempt->result_json;
                }
                try {
                    $envelope = app(QuoteStore::class)->get($store, $actor, $input['quoteId']);
                } catch (\Throwable $error) {
                    throw new QuoteUnavailable('Quote cache unavailable.', previous: $error);
                }
                if (! $envelope || $envelope['quote']['expiresAt'] <= now()->toISOString()) {
                    return $this->reject($attempt, $this->error('quote_expired', 'The reviewed quote expired. Review a fresh quote.'));
                }
                $reviewed = $envelope['quote'];
                if ($reviewed['revision'] !== $input['quoteRevision'] || $envelope['input'] != ['lines' => $input['lines'], 'payment' => $input['payment']]) {
                    return $this->reject($attempt, $this->error('quote_changed', 'The submission differs from the reviewed quote.'));
                }
                try {
                    $current = (new QuoteCalculator)->calculate($store, ['lines' => $input['lines'], 'payment' => $input['payment']]);
                } catch (ValidationException|ModelNotFoundException $error) {
                    return $this->reject($attempt, $this->error('quote_changed', 'The selections or payment changed. Review the cart.'));
                }
                $warnings = array_column($current['warnings'], 'id');
                sort($warnings, SORT_STRING);
                if ($current['blockingIssues'] || QuoteReview::changed($reviewed, $current) || $warnings !== $input['acknowledgedWarningIds']) {
                    app(QuoteStore::class)->put($store, $actor, ['lines' => $input['lines'], 'payment' => $input['payment']], $current);
                    $result = $this->error($current['blockingIssues'] ? 'stock_conflict' : 'quote_changed', 'Store data changed. Review the replacement quote and warnings.');
                    $result['body']['replacementQuote'] = $current;

                    return $this->reject($attempt, $result);
                }
                $sale = Sale::create(['checkout_attempt_id' => $attempt->id, 'team_id' => $store->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reference' => 'pending-'.Str::uuid(), 'status' => 'completed', 'occurred_at' => now(), 'currency' => 'PHP', 'subtotal_minor' => $current['subtotalMinor'], 'total_minor' => $current['totalMinor'], 'lines_json' => $current['lines']]);
                // Assign the derived reference in the creating transaction, before exposing the immutable sale.
                DB::table('sales')->where('id', $sale->id)->update(['reference' => 'SR-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);
                Payment::create(['sale_id' => $sale->id, 'method' => $input['payment']['method'], 'reference' => $input['payment']['reference'] ?? null, 'input_json' => $input['payment'], 'received_minor' => $current['totalMinor'], 'change_minor' => $current['changeMinor'], 'recorded_at' => now()]);
                $effects = array_column($current['stockEffects'], null, 'inventoryItemId');
                $stocks = InventoryItem::where('team_id', $store->id)->whereIn('id', array_keys($effects))->orderBy('id')->lockForUpdate()->get();
                foreach ($stocks as $stock) {
                    $effect = $effects[$stock->id];
                    $after = Quantity::parse($effect['after'], $stock->unit, true);
                    $operation = StockOperation::create(['team_id' => $store->id, 'sale_id' => $sale->id, 'kind' => 'sale', 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => 'Sale']);
                    StockMovement::create(['team_id' => $store->id, 'inventory_item_id' => $stock->id, 'stock_operation_id' => $operation->id, 'kind' => 'sale', 'unit' => $stock->unit, 'item_name' => $stock->name, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => 'Sale', 'before_thousandths' => $stock->balance_thousandths, 'delta_thousandths' => $after - $stock->balance_thousandths, 'after_thousandths' => $after]);
                    $stock->update(['balance_thousandths' => $after, 'revision' => $stock->revision + 1]);
                }
                $result = ['status' => 200, 'body' => ['status' => 'confirmed', 'idempotencyKey' => $input['idempotencyKey'], 'sale' => SaleData::make($sale->fresh())]];
                $attempt->update(['status' => 'confirmed', 'result_json' => $result]);

                return $result;
            });
        } catch (QueryException|QuoteUnavailable|ValidationException $error) {
            // A commit failure is ambiguous: reread durable state before returning pending.
            $durable = CheckoutAttempt::find($attempt->id);
            if ($durable && $durable->status !== 'pending') {
                return $durable->result_json;
            }
            report($error);

            return ['status' => 202, 'body' => ['status' => 'pending', 'idempotencyKey' => $input['idempotencyKey']]];
        }
    }

    /**
     * @param  array{status: int, body: array<string, mixed>}  $result
     * @return array{status: int, body: array<string, mixed>}
     */
    private function reject(CheckoutAttempt $attempt, array $result): array
    {
        $attempt->update(['status' => 'rejected', 'result_json' => $result]);

        return $result;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function error(string $code, string $message): array
    {
        return ['status' => 409, 'body' => ['error' => ['code' => $code, 'message' => $message, 'fieldErrors' => (object) [], 'issues' => []]]];
    }
}
