<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Pos\CompleteCheckout;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\User;
use App\Services\Pos\PaymentRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function basket(): array
    {
        $u = User::factory()->create();
        $store = $u->currentTeam;
        $save = new SaveCatalogItem;
        $base = ['categoryId' => null, 'active' => true, 'allowedAddonIds' => []];
        $water = $save->handle($store, $u, [...$base, 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'lowStockThreshold' => null]);
        $cheese = $save->handle($store, $u, [...$base, 'name' => 'Cheese', 'sku' => 'CH', 'kind' => 'addon', 'stockBehavior' => 'direct', 'priceMinor' => 1500, 'lowStockThreshold' => null]);
        InventoryItem::whereKey($water->stock_item_id)->update(['balance_thousandths' => 2000]);
        InventoryItem::whereKey($cheese->stock_item_id)->update(['balance_thousandths' => 3000]);
        $parts = [];
        $ids = [];
        foreach ([['Beef', 'g', 250000, '100'], ['Sauce', 'g', -5000, '10'], ['Seasoning', 'g', 1000, '0.125'], ['Packaging', 'each', 2000, '1']] as [$name,$unit,$balance,$quantity]) {
            $stock = InventoryItem::create(['team_id' => $store->id, 'name' => $name, 'kind' => $unit === 'each' ? 'supply' : 'ingredient', 'unit' => $unit, 'balance_thousandths' => $balance]);
            $ids[$name] = $stock->id;
            $parts[] = ['inventoryItemId' => $stock->id, 'unit' => $unit, 'quantity' => $quantity];
        }
        $extra = $save->handle($store, $u, [...$base, 'name' => 'Extra beef', 'sku' => 'EX', 'kind' => 'addon', 'stockBehavior' => 'recipe', 'priceMinor' => 3000, 'components' => [['inventoryItemId' => $ids['Beef'], 'unit' => 'g', 'quantity' => '50']]]);
        $burger = $save->handle($store, $u, [...$base, 'name' => 'Burger', 'sku' => 'B', 'kind' => 'food', 'priceMinor' => 10000, 'components' => $parts, 'allowedAddonIds' => [$cheese->id, $extra->id]]);

        $lines = [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $water->id, 'quantity' => 1, 'addonIds' => []], ['id' => '22222222-2222-4222-8222-222222222222', 'productId' => $burger->id, 'quantity' => 2, 'addonIds' => [$cheese->id]], ['id' => '33333333-3333-4333-8333-333333333333', 'productId' => $burger->id, 'quantity' => 1, 'addonIds' => [$extra->id]]];

        $this->actingAs($u)->withHeader('X-POS-Actor-Id', (string) $u->id);
        $url = '/'.$store->slug.'/pos';
        $payment = ['method' => 'cash', 'tenderedMinor' => 50000];
        $quote = $this->postJson($url.'/quotes', ['lines' => $lines, 'payment' => $payment])->assertOk()->json();
        $request = ['idempotencyKey' => '11111111-aaaa-4111-8111-111111111111', 'quoteId' => $quote['id'], 'quoteRevision' => $quote['revision'], 'lines' => $lines, 'payment' => $payment, 'acknowledgedWarningIds' => array_column($quote['warnings'], 'id')];

        return [$u, $store, $url, $request, $ids, $water, $cheese];
    }

    public function test_mixed_sale_commits_once_with_cash_change(): void
    {
        [$u,$store,$url,$request,$ids,$water,$cheese] = $this->basket();
        $before = InventoryItem::all()->keyBy('id');
        $sale = $this->postJson($url.'/checkouts', $request)->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('sale.totalMinor', 41000)->assertJsonPath('sale.payment.changeMinor', 9000)->assertJsonPath('sale.payment.receivedMinor', 41000)->json('sale');
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('stock_operations', 6);
        $this->assertDatabaseCount('stock_movements', 6);
        foreach (['Beef' => -100000, 'Sauce' => -35000, 'Seasoning' => 625, 'Packaging' => -1000] as $name => $balance) {
            $this->assertSame($balance, InventoryItem::find($ids[$name])->balance_thousandths);
        }
        $this->assertSame(1000, InventoryItem::find($water->stock_item_id)->balance_thousandths);
        $this->assertSame(1000, InventoryItem::find($cheese->stock_item_id)->balance_thousandths);
        foreach (InventoryItem::all() as $item) {
            $this->assertSame($before[$item->id]->revision + 1, $item->revision);
        }
        foreach ($sale['stockMovements'] as $movement) {
            $this->assertSame($sale['id'], $movement['sourceId']);
            $this->assertSame($u->id, $movement['actorId']);
        }
        Cache::flush();
        $water->update(['price_minor' => 9000]);
        $this->assertSame($sale, $this->postJson($url.'/checkouts', $request)->assertOk()->json('sale'));
        $this->getJson($url.'/attempts/'.$request['idempotencyKey'])->assertOk()->assertJsonPath('sale.id', $sale['id']);
        $this->postJson($url.'/checkouts', [...$request, 'payment' => ['method' => 'cash', 'tenderedMinor' => 60000]])->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->assertDatabaseCount('stock_movements', 6);
    }

    public function test_changed_price_is_durably_rejected_without_business_writes(): void
    {
        [$u,$store,$url,$request,$ids,$water] = $this->basket();
        $water->update(['price_minor' => 5500]);
        $first = $this->postJson($url.'/checkouts', $request)->assertStatus(409)->assertJsonPath('error.code', 'quote_changed')->json();
        $this->assertSame($first, $this->postJson($url.'/checkouts', $request)->assertStatus(409)->json());
        $this->getJson($url.'/attempts/'.$request['idempotencyKey'])->assertJsonPath('status', 'rejected');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_expired_quote_and_missing_warning_acknowledgment_reject(): void
    {
        [$u,$store,$url,$request] = $this->basket();
        $this->postJson($url.'/checkouts', [...$request, 'acknowledgedWarningIds' => []])->assertStatus(409)->assertJsonPath('error.code', 'quote_changed');
        Cache::flush();
        $this->postJson($url.'/checkouts', [...$request, 'idempotencyKey' => '22222222-aaaa-4222-8222-222222222222'])->assertStatus(409)->assertJsonPath('error.code', 'quote_expired');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_actor_change_never_registers_or_rejects_original_operation(): void
    {
        [$u,$store,$url,$request] = $this->basket();
        $this->withHeader('X-POS-Actor-Id', '999')->postJson($url.'/checkouts', $request)->assertStatus(409)->assertJsonPath('error.code', 'session_changed');
        $this->assertDatabaseCount('checkout_attempts', 0);
    }

    public function test_posting_failure_rolls_back_all_business_writes_and_preserves_pending(): void
    {
        [$u,$store,$url,$request] = $this->basket();
        $before = InventoryItem::all()->toArray();
        InventoryItem::updated(function () {
            throw new \RuntimeException('Injected posting interruption');
        });
        try {
            (new CompleteCheckout)->handle($store, $u, $request);
            $this->fail('Expected interruption');
        } catch (\RuntimeException $error) {
            $this->assertSame('Injected posting interruption', $error->getMessage());
        } finally {
            InventoryItem::flushEventListeners();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('stock_operations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->getJson($url.'/attempts/'.$request['idempotencyKey'])->assertJsonPath('status', 'pending');
        $this->postJson($url.'/checkouts', $request)->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_duplicate_reference_warns_per_store_provider_and_exact_case_even_after_reversal(): void
    {
        [$u,$store,$url,$request] = $this->basket();
        $payment = ['method' => 'gcash', 'receivedConfirmed' => true, 'reference' => 'REF-1'];
        $quote = $this->postJson($url.'/quotes', ['lines' => $request['lines'], 'payment' => $payment])->assertOk()->json();
        $request = [...$request, 'quoteId' => $quote['id'], 'quoteRevision' => $quote['revision'], 'payment' => $payment, 'acknowledgedWarningIds' => array_column($quote['warnings'], 'id')];
        $sale = $this->postJson($url.'/checkouts', $request)->assertOk()->json('sale');
        Sale::find($sale['id'])->update(['status' => 'reversed']);
        $rules = new PaymentRules;
        $this->assertCount(1, $rules->warnings($store, $payment));
        $this->assertSame([], $rules->warnings($store, [...$payment, 'method' => 'maya']));
        $this->assertSame([], $rules->warnings($store, [...$payment, 'reference' => 'ref-1']));
        $other = User::factory()->create();
        $this->assertSame([], $rules->warnings($other->currentTeam, $payment));
    }
}
