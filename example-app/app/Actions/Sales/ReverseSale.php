<?php

namespace App\Actions\Sales;

use App\Data\SaleReversalData;
use App\Models\InventoryItem;
use App\Models\ReversalAttempt;
use App\Models\Sale;
use App\Models\SaleReversal;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\Team;
use App\Models\User;
use App\Services\Pos\IdempotencyConflict;
use App\Services\Sales\RestockEligibility;
use App\Services\Sales\ReversalAttempts;
use App\Services\Sales\ReversalRequest;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ReverseSale
{
    /** @param array<string, mixed> $input
     * @return array{status:int, body:array<string, mixed>}
     */
    public function handle(Team $store, User $actor, Sale $sale, array $input): array
    {
        abort_unless($sale->team_id === $store->id, 404);
        $canonical = ReversalRequest::canonical($sale->id, $input);
        try {
            $attempt = (new ReversalAttempts)->register($store, $actor, $sale, $canonical);
        } catch (IdempotencyConflict $error) {
            return $this->error('idempotency_conflict', $error->getMessage(), 409);
        } catch (QueryException $error) {
            report($error);

            return $this->error('reversal_status_unknown', 'Refund recording status is unknown. Check this sale.', 503);
        }
        if ($attempt->status !== 'pending') {
            return $attempt->result_json;
        }
        try {
            return StoreCatalogTransaction::run($store, function () use ($store, $actor, $sale, $attempt, $canonical) {
                $attempt = ReversalAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($attempt->status !== 'pending') {
                    return $attempt->result_json;
                }
                $sale = Sale::where('team_id', $store->id)->whereKey($sale->id)->lockForUpdate()->firstOrFail();
                if ($sale->status === 'reversed' || SaleReversal::where('sale_id', $sale->id)->exists()) {
                    return $this->reject($attempt, $this->error('already_reversed', 'This sale already has a recorded full refund.', 409));
                }
                $eligible = array_column((new RestockEligibility)->forSale($sale), null, 'inventoryItemId');
                $selected = array_column($canonical['restock'], null, 'inventoryItemId');
                $stocks = InventoryItem::where('team_id', $store->id)->whereIn('id', array_keys($selected))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $after = [];
                foreach ($canonical['restock'] as $index => $part) {
                    $id = $part['inventoryItemId'];
                    $quantity = Quantity::parse($part['quantity'], 'each');
                    if (! isset($eligible[$id],$stocks[$id]) || $quantity > Quantity::parse($eligible[$id]['maxQuantity'], 'each')) {
                        return $this->reject($attempt, $this->error('validation_failed', 'Review the eligible returned quantities.', 422, ['restock.'.$index.'.quantity' => ['Return only the original direct-stock quantity sold.']]));
                    }
                    try {
                        $after[$id] = Quantity::add($stocks[$id]->balance_thousandths, $quantity);
                    } catch (ValidationException $error) {
                        return $this->reject($attempt, $this->error('numeric_range_exceeded', 'Returned stock exceeds the supported balance.', 422, ['restock.'.$index.'.quantity' => ['numeric_range_exceeded']]));
                    }
                }
                $reversal = SaleReversal::create(['team_id' => $store->id, 'sale_id' => $sale->id, 'reversal_attempt_id' => $attempt->id, 'request_json' => $canonical, 'request_hash' => $attempt->request_hash, 'total_minor' => $sale->total_minor, 'refund_method' => $canonical['refundMethod'], 'refund_reference' => $canonical['refundReference'], 'reason' => $canonical['reason'], 'refund_confirmed' => true, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'occurred_at' => now()]);
                foreach ($stocks as $stock) {
                    $operation = StockOperation::create(['team_id' => $store->id, 'sale_id' => $sale->id, 'sale_reversal_id' => $reversal->id, 'kind' => 'reversal', 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $canonical['reason']]);
                    StockMovement::create(['team_id' => $store->id, 'inventory_item_id' => $stock->id, 'stock_operation_id' => $operation->id, 'kind' => 'reversal', 'unit' => 'each', 'item_name' => $eligible[$stock->id]['name'], 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'reason' => $canonical['reason'], 'before_thousandths' => $stock->balance_thousandths, 'delta_thousandths' => $after[$stock->id] - $stock->balance_thousandths, 'after_thousandths' => $after[$stock->id]]);
                    $stock->update(['balance_thousandths' => $after[$stock->id], 'revision' => $stock->revision + 1]);
                }
                $sale->update(['status' => 'reversed']);
                $result = ['status' => 200, 'body' => ['status' => 'recorded', 'saleId' => $sale->id, 'idempotencyKey' => $canonical['idempotencyKey'], 'reversal' => SaleReversalData::make($reversal)]];
                $attempt->update(['status' => 'recorded', 'result_json' => $result]);

                return $result;
            });
        } catch (QueryException|ValidationException $error) {
            report($error);
            try {
                $durable = ReversalAttempt::findOrFail($attempt->id);
                if ($durable->status !== 'pending') {
                    return $durable->result_json;
                }
            } catch (\Throwable $readError) {
                report($readError);

                return $this->error('reversal_status_unknown', 'Refund recording status is unknown. Check this sale.', 503);
            }

            return ['status' => 202, 'body' => ['status' => 'pending', 'saleId' => $sale->id, 'idempotencyKey' => $canonical['idempotencyKey']]];
        }
    }

    /** @param array{status:int, body:array<string, mixed>} $result
     * @return array{status:int, body:array<string, mixed>}
     */
    private function reject(ReversalAttempt $attempt, array $result): array
    {
        $attempt->update(['status' => 'rejected', 'result_json' => $result]);

        return $result;
    }

    /** @param array<string, list<string>> $fields
     * @return array{status:int, body:array<string, mixed>}
     */
    private function error(string $code, string $message, int $status, array $fields = []): array
    {
        return ['status' => $status, 'body' => ['error' => ['code' => $code, 'message' => $message, 'fieldErrors' => $fields ?: (object) [], 'issues' => []]]];
    }
}
