<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\ReverseSale;
use App\Data\SaleData;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SalesBasket;
use Tests\TestCase;

class ReversalTest extends TestCase
{
    use RefreshDatabase, SalesBasket;

    protected function completed(): array
    {
        [$u, $store, $url, $request, $ids, $water, $cheese] = $this->basket();
        $sale = $this->postJson($url.'/checkouts', $request)->assertOk()->json('sale');
        $input = ['idempotencyKey' => 'aaaaaaaa-1111-4111-8111-111111111111', 'reason' => ' Returned ', 'refundConfirmed' => true, 'refundMethod' => 'maya', 'refundReference' => ' REF ', 'restock' => [['inventoryItemId' => $water->stock_item_id, 'quantity' => '1.000'], ['inventoryItemId' => $cheese->stock_item_id, 'quantity' => '1.000']]];

        return [$u, $store, '/'.$store->slug.'/sales/'.$sale['id'], $input, $sale, $request, $ids, $water, $cheese];
    }

    public function test_mixed_refund_restocks_selected_direct_quantities_once(): void
    {
        [$u,$store,$url,$input,$original,$checkout,$ids,$water,$cheese] = $this->completed();
        $before = InventoryItem::all()->keyBy('id');
        $response = $this->postJson($url.'/reversals', $input)->assertOk()->assertJsonPath('status', 'recorded')->assertJsonPath('reversal.totalMinor', 41000)->assertJsonPath('reversal.reason', 'Returned')->assertJsonPath('reversal.refundReference', 'REF')->json();
        $this->postJson($url.'/reversals', $input)->assertOk()->assertExactJson($response);
        $this->assertDatabaseCount('sale_reversals', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('stock_movements', 8);
        foreach ([$water->stock_item_id, $cheese->stock_item_id] as $id) {
            $stock = InventoryItem::find($id);
            $this->assertSame(2000, $stock->balance_thousandths);
            $this->assertSame($before[$id]->revision + 1, $stock->revision);
        }
        foreach (['Beef' => -100000, 'Sauce' => -35000, 'Seasoning' => 625, 'Packaging' => -1000] as $name => $balance) {
            $this->assertSame($balance, InventoryItem::find($ids[$name])->balance_thousandths);
        }
        $current = SaleData::make(Sale::find($original['id']));
        $this->assertSame($original['lines'], $current['lines']);
        $this->assertSame($original['payment'], $current['payment']);
        $this->assertSame('reversed', $current['status']);
        $this->assertSame($response['reversal']['id'], $current['stockMovements'][6]['sourceId']);
        $this->assertSame($original['id'], $current['stockMovements'][0]['sourceId']);
        $this->getJson('/'.$store->slug.'/pos/attempts/'.$checkout['idempotencyKey'])->assertExactJson(['status' => 'confirmed', 'idempotencyKey' => $checkout['idempotencyKey'], 'sale' => $original]);
        $this->postJson('/'.$store->slug.'/pos/checkouts', $checkout)->assertExactJson(['status' => 'confirmed', 'idempotencyKey' => $checkout['idempotencyKey'], 'sale' => $original]);
        $this->getJson($url.'/reversal')->assertJsonPath('status', 'recorded');
        $this->getJson($url.'/reversal-attempts/'.$input['idempotencyKey'])->assertExactJson($response);
    }

    public static function invalidInputs(): array
    {
        return ['empty reason' => [['reason' => ' ']], 'long reason' => [['reason' => str_repeat('a', 1001)]], 'unconfirmed' => [['refundConfirmed' => false]], 'method' => [['refundMethod' => 'card']], 'long reference' => [['refundReference' => str_repeat('a', 256)]], 'fraction' => [['quantity' => '0.500']], 'zero' => [['quantity' => '0']], 'negative' => [['quantity' => '-1']], 'excess' => [['quantity' => '3.000']], 'overflow' => [['quantity' => '9007199254741']], 'unknown' => [['inventoryItemId' => 999999]]];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_refunds_have_no_business_effects(array $changes): void
    {
        [,,$url,$input] = $this->completed();
        if (isset($changes['quantity']) || isset($changes['inventoryItemId'])) {
            $input['restock'][1] = [...$input['restock'][1], ...$changes];
        } else {
            $input = [...$input, ...$changes];
        }
        $this->postJson($url.'/reversals', $input)->assertStatus(422)->assertJsonStructure(['error' => ['code', 'fieldErrors']]);
        $this->assertDatabaseCount('sale_reversals', 0);
        $this->assertDatabaseCount('stock_movements', 6);
        $this->assertSame('completed', Sale::first()->status);
    }

    public function test_all_refund_methods_allow_no_stock_and_optional_reference_despite_checkout_configuration(): void
    {
        foreach (['cash', 'gcash', 'maya'] as $method) {
            [$u,$store,$url,$input] = $this->completed();
            config(['pos.store_payment_methods.'.$store->id => ['cash']]);
            $this->postJson($url.'/reversals', [...$input, 'refundMethod' => $method, 'refundReference' => ' ', 'restock' => []])->assertOk()->assertJsonPath('reversal.refundMethod', $method)->assertJsonPath('reversal.refundReference', null)->assertJsonPath('reversal.restock', []);
        }
        $this->assertDatabaseCount('sale_reversals', 3);
        $this->assertDatabaseCount('stock_movements', 18);
    }

    public function test_changed_payload_and_other_keys_cannot_repeat_a_refund(): void
    {
        [,,$url,$input] = $this->completed();
        $this->postJson($url.'/reversals', $input)->assertOk();
        foreach (['reason' => 'Another', 'refundMethod' => 'cash', 'refundReference' => 'Other', 'restock' => []] as $field => $value) {
            $this->postJson($url.'/reversals', [...$input, $field => $value])->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        }
        $this->postJson($url.'/reversals', [...$input, 'idempotencyKey' => 'bbbbbbbb-1111-4111-8111-111111111111'])->assertStatus(409)->assertJsonPath('error.code', 'already_reversed');
        $this->assertDatabaseCount('sale_reversals', 1);
        $this->assertDatabaseCount('stock_movements', 8);
    }

    public function test_rejected_key_stays_rejected_after_stock_changes(): void
    {
        [$u,$store,$url,$input,,,, $water] = $this->completed();
        DB::table('inventory_items')->where('id', $water->stock_item_id)->update(['balance_thousandths' => 9007199254740000]);
        $result = $this->postJson($url.'/reversals', $input)->assertStatus(422)->json();
        $this->getJson($url.'/reversal-attempts/'.$input['idempotencyKey'])->assertJsonPath('status', 'rejected');
        DB::table('inventory_items')->where('id', $water->stock_item_id)->update(['balance_thousandths' => 1000]);
        $this->postJson($url.'/reversals', $input)->assertStatus(422)->assertExactJson($result);
        $this->assertDatabaseCount('sale_reversals', 0);
        $this->postJson($url.'/reversals', [...$input, 'idempotencyKey' => 'bbbbbbbb-1111-4111-8111-111111111111'])->assertOk();
    }

    public function test_duplicates_recipe_supplies_and_missing_history_are_rejected_but_inactive_direct_stock_is_valid(): void
    {
        [$u,$store,$url,$input,,,$ids,$water] = $this->completed();
        $this->postJson($url.'/reversals', [...$input, 'restock' => [$input['restock'][0], $input['restock'][0]]])->assertStatus(422);
        $this->postJson($url.'/reversals', [...$input, 'restock' => [['inventoryItemId' => $ids['Packaging'], 'quantity' => '1.000']]])->assertStatus(422);
        DB::table('inventory_items')->where('id', $water->stock_item_id)->update(['active' => false]);
        $this->postJson($url.'/reversals', [...$input, 'idempotencyKey' => 'bbbbbbbb-1111-4111-8111-111111111111'])->assertOk();
    }

    public function test_interruption_rolls_back_stock_status_and_refund_but_retains_registration(): void
    {
        [$u,$store,$url,$input,$original] = $this->completed();
        $before = InventoryItem::all()->toArray();
        InventoryItem::updated(fn () => throw new \RuntimeException('Interrupted'));
        try {
            (new ReverseSale)->handle($store, $u, Sale::find($original['id']), $input);
            $this->fail('Expected interruption');
        } catch (\RuntimeException $e) {
            $this->assertSame('Interrupted', $e->getMessage());
        } finally {
            InventoryItem::flushEventListeners();
        }
        $this->assertDatabaseCount('sale_reversals', 0);
        $this->assertDatabaseCount('stock_movements', 6);
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertSame('completed', Sale::first()->status);
        $this->getJson($url.'/reversal-attempts/'.$input['idempotencyKey'])->assertJsonPath('status', 'pending');
        $this->postJson($url.'/reversals', $input)->assertOk();
    }

    public function test_refund_reads_and_writes_are_scoped_to_verified_owner_and_actor(): void
    {
        [$u,$store,$url,$input,$original] = $this->completed();
        $other = User::factory()->create();
        $this->actingAs($other)->withHeader('X-POS-Actor-Id', (string) $other->id);
        $this->postJson($url.'/reversals', $input)->assertForbidden();
        $this->getJson($url.'/reversal')->assertForbidden();
        $this->getJson($url.'/reversal-attempts/'.$input['idempotencyKey'])->assertForbidden();
        $foreign = '/'.$other->currentTeam->slug.'/sales/'.$original['id'];
        $this->postJson($foreign.'/reversals', $input)->assertNotFound();
        $this->getJson($foreign.'/reversal')->assertNotFound();
        $this->actingAs($u)->withHeader('X-POS-Actor-Id', (string) $other->id);
        $this->postJson($url.'/reversals', $input)->assertStatus(409)->assertJsonPath('error.code', 'session_changed');
        $this->assertDatabaseCount('reversal_attempts', 0);
        $this->withHeader('X-POS-Actor-Id', (string) $u->id);
        $this->getJson($url.'/reversal')->assertJsonPath('status', 'not_recorded');
        $this->getJson($url.'/reversal-attempts/'.$input['idempotencyKey'])->assertJsonPath('status', 'not_found');
        $this->assertDatabaseCount('reversal_attempts', 0);
    }

    public function test_same_actor_key_cannot_be_reused_for_another_sale(): void
    {
        [$u,$store,$url,$input,$original,$checkout] = $this->completed();
        $this->postJson($url.'/reversals', [...$input, 'restock' => []])->assertOk();
        $checkout['idempotencyKey'] = 'cccccccc-1111-4111-8111-111111111111';
        $checkout['lines'] = [$checkout['lines'][0]];
        $quote = $this->postJson('/'.$store->slug.'/pos/quotes', ['lines' => $checkout['lines'], 'payment' => $checkout['payment']])->assertOk()->json();
        $checkout['quoteId'] = $quote['id'];
        $checkout['quoteRevision'] = $quote['revision'];
        $checkout['acknowledgedWarningIds'] = array_column($quote['warnings'], 'id');
        $next = $this->postJson('/'.$store->slug.'/pos/checkouts', $checkout)->assertOk()->json('sale.id');
        $this->postJson('/'.$store->slug.'/sales/'.$next.'/reversals', [...$input, 'restock' => []])->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->getJson('/'.$store->slug.'/sales/'.$next.'/reversal-attempts/'.$input['idempotencyKey'])->assertJsonPath('status', 'not_found');
    }

    public function test_missing_historical_linkage_fails_without_guessing_from_current_catalog(): void
    {
        [,,$url,$input,,,, $water] = $this->completed();
        DB::table('stock_movements')->where('inventory_item_id', $water->stock_item_id)->delete();
        $this->postJson($url.'/reversals', $input)->assertStatus(422);
        $this->assertDatabaseCount('sale_reversals', 0);
        $this->assertSame('completed', Sale::first()->status);
    }
}
