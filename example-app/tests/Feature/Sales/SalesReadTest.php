<?php

namespace Tests\Feature\Sales;

use App\Data\SaleData;
use App\Models\CheckoutAttempt;
use App\Models\ReversalAttempt;
use App\Models\Sale;
use App\Models\SaleReversal;
use App\Models\User;
use App\Services\Sales\RestockEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesBasket;
use Tests\TestCase;

class SalesReadTest extends TestCase
{
    use RefreshDatabase, SalesBasket;

    public function test_historical_detail_and_eligibility_use_original_direct_snapshots(): void
    {
        $this->withoutVite();
        [$u, $store, $url, $request, $ids, $water, $cheese] = $this->basket();
        $original = $this->postJson($url.'/checkouts', $request)->assertOk()->json('sale');
        DB::table('catalog_items')->where('team_id', $store->id)->update(['name' => 'Edited', 'price_minor' => 1, 'active' => false]);
        DB::table('inventory_items')->where('id', $cheese->stock_item_id)->update(['name' => 'Changed cheese', 'active' => false]);
        $sale = Sale::findOrFail($original['id']);
        $eligible = (new RestockEligibility)->forSale($sale);
        $this->assertSame([$water->stock_item_id, $cheese->stock_item_id], array_column($eligible, 'inventoryItemId'));
        $this->assertSame(['1.000', '2.000'], array_column($eligible, 'maxQuantity'));
        $this->assertSame('Cheese', $eligible[1]['name']);
        $this->assertFalse($eligible[1]['active']);
        $this->assertSame($original['lines'], SaleData::make($sale)['lines']);
        $this->get('/'.$store->slug.'/sales/'.$sale->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('sales/show', false)->where('sale.totalMinor', 41000)->where('sale.payment.changeMinor', 9000)->has('restockCandidates', 2)->where('storeName', $store->name));
    }

    public function test_filters_use_manila_sale_date_and_stable_pagination(): void
    {
        $this->withoutVite();
        [$u, $store, $url, $request] = $this->basket();
        $this->postJson($url.'/checkouts', $request)->assertOk();
        $sale = Sale::firstOrFail();
        DB::table('sales')->where('id', $sale->id)->update(['occurred_at' => '2026-11-06 16:00:00', 'reference' => 'SR-100%_literal']);
        $base = '/'.$store->slug.'/sales';
        $this->get($base.'?dateTo=2026-11-07')->assertOk();
        $this->get($base.'?dateFrom=2026-11-07&dateTo=2026-11-07&reference=%25_')->assertOk()->assertInertia(fn (Assert $page) => $page->component('sales/index', false)->has('sales.data', 1)->where('sales.perPage', 25)->missing('sales.data.0.lines')->missing('sales.data.0.stockMovements')->where('sales.data.0.paymentMethod', 'cash')->where('sales.data.0.refundedMinor', 0)->where('sales.data.0.netMinor', 41000));
        foreach (['2026-11-06 15:59:59', '2026-11-07 16:00:00'] as $outside) {
            DB::table('sales')->where('id', $sale->id)->update(['occurred_at' => $outside]);
            $this->get($base.'?dateFrom=2026-11-07&dateTo=2026-11-07')->assertInertia(fn (Assert $page) => $page->where('sales.total', 0));
        }
        $this->get($base.'?dateFrom=bad')->assertSessionHasErrors('dateFrom');
        $this->get($base.'?dateFrom=2026-11-08&dateTo=2026-11-07')->assertSessionHasErrors('dateTo');
        $this->get($base.'?reference=unmatched')->assertInertia(fn (Assert $page) => $page->where('sales.total', 0));
    }

    public function test_sales_are_owner_verified_and_store_scoped(): void
    {
        $this->withoutVite();
        [$u, $store, $url, $request] = $this->basket();
        $id = $this->postJson($url.'/checkouts', $request)->assertOk()->json('sale.id');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/'.$store->slug.'/sales')->assertForbidden();
        $this->get('/'.$other->currentTeam->slug.'/sales/'.$id)->assertNotFound();
        $other->forceFill(['email_verified_at' => null])->save();
        $this->get('/'.$other->currentTeam->slug.'/sales')->assertRedirect(route('verification.notice'));
    }

    public function test_pagination_ties_are_stable_and_relationship_queries_are_bounded(): void
    {
        $this->withoutVite();
        [$u, $store, $url, $request] = $this->basket();
        $this->postJson($url.'/checkouts', $request)->assertOk();
        $sale = Sale::firstOrFail();
        $base = '/'.$store->slug.'/sales';
        DB::enableQueryLog();
        $this->get($base)->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($n = 0; $n < 26; $n++) {
            $attempt = CheckoutAttempt::first()->replicate();
            $attempt->idempotency_key = 'history-'.$n;
            $attempt->save();
            $attributes = $sale->getAttributes();
            unset($attributes['id']);
            $attributes['checkout_attempt_id'] = $attempt->id;
            $attributes['reference'] = 'history-'.$n;
            $id = DB::table('sales')->insertGetId($attributes);
            $payment = $sale->payment->getAttributes();
            unset($payment['id']);
            $payment['sale_id'] = $id;
            DB::table('payments')->insert($payment);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($base)->assertInertia(fn (Assert $page) => $page->where('sales.total', 27)->has('sales.data', 25)->where('sales.data.0.id', $id)->where('sales.data.24.id', $id - 24));
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 2, $many);
        $this->get($base.'?page=2')->assertInertia(fn (Assert $page) => $page->where('sales.currentPage', 2)->has('sales.data', 2));
    }

    public function test_original_and_adjusted_amounts_are_distinct(): void
    {
        $this->withoutVite();
        [$u, $store, $url, $request] = $this->basket();
        $this->postJson($url.'/checkouts', $request)->assertOk();
        $sale = Sale::firstOrFail();
        $input = ['restock' => []];
        $attempt = ReversalAttempt::create(['team_id' => $store->id, 'actor_id' => $u->id, 'sale_id' => $sale->id, 'idempotency_key' => 'read-test', 'request_json' => $input, 'request_hash' => hash('sha256', '{}'), 'status' => 'pending']);
        SaleReversal::create(['team_id' => $store->id, 'actor_id' => $u->id, 'actor_name' => $u->name, 'sale_id' => $sale->id, 'reversal_attempt_id' => $attempt->id, 'request_json' => $input, 'request_hash' => $attempt->request_hash, 'total_minor' => 41000, 'refund_method' => 'maya', 'refund_reference' => null, 'reason' => 'Returned', 'refund_confirmed' => true, 'occurred_at' => now()]);
        $sale->update(['status' => 'reversed']);
        $this->get('/'.$store->slug.'/sales?payment=cash&status=reversed')->assertInertia(fn (Assert $page) => $page->has('sales.data', 1)->where('sales.data.0.totalMinor', 41000)->where('sales.data.0.refundedMinor', 41000)->where('sales.data.0.netMinor', 0));
        $this->get('/'.$store->slug.'/sales?payment=maya')->assertInertia(fn (Assert $page) => $page->where('sales.total', 0));
        $this->get('/'.$store->slug.'/sales/'.$sale->id.'?payment=cash&page=2')->assertInertia(fn (Assert $page) => $page->where('sale.payment.input.tenderedMinor', 50000)->where('sale.payment.changeMinor', 9000)->where('sale.reversal.refundMethod', 'maya')->where('returnTo.page', 2)->where('returnTo.filters.payment', 'cash'));
    }
}
