<?php

namespace App\Actions\Pos;

use App\Data\CatalogData;
use App\Data\InventoryData;
use App\Models\CatalogItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Catalog\StockDemand;
use App\Services\Pos\QuoteStore;
use App\Support\Quantity;
use App\Support\StoreCatalogTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreateQuote
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function handle(Team $store, User $actor, array $validated): array
    {
        Validator::make($validated, ['lines' => 'required|array|min:1', 'payment' => ['present', function ($attribute, $value, $fail) {
            if ($value !== null) {
                $fail('Payment is unavailable in this checkout stage.');
            }
        }]])->validate();

        return StoreCatalogTransaction::run($store, function () use ($store, $actor, $validated) {
            $effects = (new StockDemand)->calculate($store, $validated['lines']);
            $ids = [];
            foreach ($validated['lines'] as $line) {
                $ids[] = $line['productId'];
                array_push($ids, ...$line['addonIds']);
            }
            $items = CatalogItem::with(['addons', 'components'])->where('team_id', $store->id)->whereIn('id', array_unique($ids))->get()->keyBy('id');
            $lines = [];
            $catalogRevisions = $recipeRevisions = [];
            $total = 0;
            $inputLines = [];
            foreach ($validated['lines'] as $line) {
                sort($line['addonIds'], SORT_NUMERIC);
                $inputLines[] = ['id' => $line['id'], 'productId' => (int) $line['productId'], 'quantity' => (int) $line['quantity'], 'addonIds' => array_map('intval', $line['addonIds'])];
                $item = $items[$line['productId']];
                $price = $item->price_minor;
                $addons = [];
                $components = CatalogData::item($item)['components'] ?? [];
                foreach ([$item->id, ...$line['addonIds']] as $id) {
                    $selected = $items[$id];
                    $catalogRevisions[(string) $id] = (string) $selected->revision;
                    if ($selected->stock_item_id === null) {
                        $recipeRevisions[(string) $id] = (string) $selected->recipe_revision;
                    }
                    if ($id === $item->id) {
                        continue;
                    }
                    $parts = CatalogData::item($selected)['components'] ?? [];
                    $addons[] = ['id' => $id, 'name' => $selected->name, 'unitPriceMinor' => $selected->price_minor, 'directStockItemId' => $selected->stock_item_id, 'components' => $parts];
                    $components = [...$components, ...$parts];
                    $price = Quantity::add($price, $selected->price_minor);
                }
                $lineTotal = Quantity::multiply($price, (int) $line['quantity']);
                $total = Quantity::add($total, $lineTotal);
                $lines[] = ['lineId' => $line['id'], 'productId' => $item->id, 'name' => $item->name, 'sku' => $item->sku, 'quantity' => (int) $line['quantity'], 'unitPriceMinor' => $item->price_minor, 'addons' => $addons, 'totalMinor' => $lineTotal, 'components' => $components, 'directStockItemId' => $item->stock_item_id];
            }
            $blocking = $warnings = [];
            foreach ($effects as $effect) {
                if (Quantity::parse($effect['after'], $effect['unit'], true) >= 0) {
                    continue;
                }
                $block = $effect['policy'] === 'block';
                $issue = ['id' => ($block ? 'direct-stock-' : 'recipe-stock-').$effect['inventoryItemId'], 'code' => $block ? 'direct_stock_shortage' : 'recipe_stock_warning', 'message' => $effect['inventoryItemName'].': available '.$effect['before'].', requested '.$effect['demand'].', projected '.$effect['after'].' '.$effect['unit'], 'field' => null, 'inventoryItemId' => $effect['inventoryItemId']];
                if ($block) {
                    $blocking[] = $issue;
                } else {
                    $warnings[] = $issue;
                }
            }
            $quote = ['id' => (string) Str::uuid(), 'revision' => (string) Str::uuid(), 'expiresAt' => now()->addSeconds(300)->toISOString(), 'business' => InventoryData::business($store), 'lines' => $lines, 'subtotalMinor' => $total, 'totalMinor' => $total, 'payment' => null, 'changeMinor' => null, 'catalogRevisions' => (object) $catalogRevisions, 'recipeRevisions' => (object) $recipeRevisions, 'stockEffects' => $effects, 'blockingIssues' => $blocking, 'warnings' => $warnings];
            app(QuoteStore::class)->put($store, $actor, ['lines' => $inputLines, 'payment' => null], $quote);

            return $quote;
        });
    }
}
