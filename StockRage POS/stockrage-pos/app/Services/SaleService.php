<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleService
{
    public function __construct(private StockService $stock, private BundleService $bundles)
    {
    }

    /**
     * Save a sale and deduct stock inside ONE database transaction,
     * so a half-finished sale can never exist.
     *
     * The total is always recalculated here from database prices —
     * we never trust a total sent by the browser.
     *
     * @param  array<int, array{product_id:int, qty:int}>  $items
     * @param  array<int, array{size:string, parts:array<int, array{product_id:int, pieces:int}>}>  $bundles  see BundleService
     *
     * @throws RuntimeException when stock or payment is not enough
     */
    public function checkout(
        array $items,
        float|string $amountPaid,
        string $paymentMethod,
        ?CarbonInterface $at = null,
        ?int $userId = null,
        array $bundles = [],
    ): Sale {
        $items = $this->mergeDuplicates($items);

        if ($items === [] && $bundles === []) {
            throw new RuntimeException('The cart is empty');
        }

        return DB::transaction(function () use ($items, $bundles, $amountPaid, $paymentMethod, $at, $userId) {
            $sale = new Sale([
                'user_id' => $userId,
                'total' => 0,
                'amount_paid' => $amountPaid,
                'change_amount' => 0,
                'payment_method' => $paymentMethod,
                'status' => Sale::COMPLETED,
            ]);
            if ($at) {
                $sale->created_at = $at;
                $sale->updated_at = $at;
            }
            $sale->save();

            // One list of lines: normal items at the product's price, bundle parts at their share of the tray price.
            $lines = [];
            foreach ($items as $item) {
                $lines[] = ['product_id' => $item['product_id'], 'qty' => $item['qty'], 'price' => null, 'cost' => null, 'note' => null];
            }
            foreach ($bundles as $bundle) {
                foreach ($this->bundles->resolve($bundle) as $line) {
                    $lines[] = ['product_id' => $line['product']->id, 'qty' => $line['qty'], 'price' => $line['price'], 'cost' => $line['cost'], 'note' => $line['note']];
                }
            }
            usort($lines, fn ($a, $b) => $a['product_id'] <=> $b['product_id']); // stable lock order

            $totalCents = 0;

            foreach ($lines as $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($line['product_id']);

                if (! $product->is_active) {
                    throw new RuntimeException($product->name.' is not available');
                }

                $price = $line['price'] ?? (float) $product->price;
                $cost = $line['cost'] ?? (float) ($product->cost_price ?? 0);
                $totalCents += (int) round($price * 100) * $line['qty'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'qty' => $line['qty'],
                    'price' => $price,
                    'cost' => $cost,
                    'note' => $line['note'],
                ]);

                $this->stock->change($product, -$line['qty'], 'sale', 'Sale #'.$sale->id.($line['note'] ? ' ('.$line['note'].')' : ''), $at);
            }

            $total = $totalCents / 100;
            $paidCents = (int) round(((float) $amountPaid) * 100);

            if ($paidCents < $totalCents) {
                throw new RuntimeException('Payment is not enough');
            }

            $sale->update([
                'total' => $total,
                'change_amount' => ($paidCents - $totalCents) / 100,
            ]);

            return $sale->load('items.product');
        });
    }

    /** Cancel a completed sale: put the stock back and mark it voided (with an optional reason). */
    public function void(Sale $sale, ?string $reason = null, ?int $userId = null): Sale
    {
        $reason = $reason !== null ? trim($reason) : null;

        return DB::transaction(function () use ($sale, $reason, $userId) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($locked->isVoided()) {
                throw new RuntimeException('Sale #'.$locked->id.' is already voided');
            }

            foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                $product = Product::query()->findOrFail($item->product_id);
                $this->stock->change($product, +$item->qty, 'void', 'Void Sale #'.$locked->id.($reason ? ' - '.$reason : ''));
            }

            $locked->update([
                'status' => Sale::VOIDED,
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason ?: null,
            ]);

            return $locked->load('items.product');
        });
    }

    /** @return array<int, array{product_id:int, qty:int}> sorted by product id */
    private function mergeDuplicates(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (int) $item['qty'];
        }
        ksort($merged);

        $out = [];
        foreach ($merged as $id => $qty) {
            $out[] = ['product_id' => $id, 'qty' => $qty];
        }

        return $out;
    }
}
