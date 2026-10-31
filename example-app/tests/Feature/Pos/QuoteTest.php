<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Teams\CreateTeam;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Pos\QuoteStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_failure_returns_an_error_and_never_a_reusable_quote(): void
    {
        $user = User::factory()->create();
        $water = (new SaveCatalogItem)->handle($user->currentTeam, $user, ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
        Cache::shouldReceive('add')->once()->andReturn(false);
        $this->actingAs($user)->postJson('/'.$user->currentTeam->slug.'/pos/quotes', ['lines' => [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $water->id, 'quantity' => 1, 'addonIds' => []]], 'payment' => null])->assertStatus(503)->assertJsonPath('error.code', 'quote_unavailable')->assertJsonMissingPath('id');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_quotes_use_current_prices_and_stock_without_writes_and_preserve_selected_store(): void
    {
        $user = User::factory()->create();
        $store = $user->currentTeam;
        $water = (new SaveCatalogItem)->handle($store, $user, ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
        InventoryItem::whereKey($water->stock_item_id)->update(['balance_thousandths' => 2000]);
        $selected = app(CreateTeam::class)->handle($user, 'Second');
        $user->switchTeam($selected);
        $before = InventoryItem::all()->toArray();
        $line = ['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $water->id, 'quantity' => 3, 'addonIds' => []];
        $url = '/'.$store->slug.'/pos/quotes';
        $quote = $this->actingAs($user)->postJson($url, ['lines' => [$line], 'payment' => null, 'totalMinor' => 1])->assertOk()->assertJsonPath('totalMinor', 15000)->assertJsonPath('payment', null)->assertJsonPath('stockEffects.0.after', '-1.000')->assertJsonPath('stockEffects.0.inventoryItemName', 'Water')->assertJsonCount(1, 'blockingIssues')->json();
        $this->assertSame($selected->id, $user->fresh()->current_team_id);
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertDatabaseCount('stock_operations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $cache = app(QuoteStore::class);
        $this->assertSame($quote, json_decode(json_encode($cache->get($store, $user, $quote['id'])['quote']), true));
        $other = User::factory()->create();
        $this->assertNull($cache->get($store, $other, $quote['id']));
        $this->assertNull($cache->get($selected, $user, $quote['id']));
        $this->travel(301)->seconds();
        $this->assertNull($cache->get($store, $user, $quote['id']));
        $this->travelBack();
        $water->update(['price_minor' => 11000]);
        $this->postJson($url, ['lines' => [$line], 'payment' => null])->assertOk()->assertJsonPath('totalMinor', 33000);
        foreach ([['lines' => [], 'payment' => null], ['lines' => [$line, $line], 'payment' => null], ['lines' => [[...$line, 'quantity' => 0]], 'payment' => null], ['lines' => [$line], 'payment' => ['method' => 'cash', 'tenderedMinor' => 50000]]] as $input) {
            $this->postJson($url, $input)->assertUnprocessable()->assertJsonStructure(['error' => ['code', 'message', 'fieldErrors', 'issues']]);
        }
        $this->postJson($url, ['lines' => [[...$line, 'quantity' => 9007199254740]], 'payment' => null])->assertUnprocessable()->assertJsonPath('error.code', 'numeric_range_exceeded');
        $foreign = (new SaveCatalogItem)->handle($other->currentTeam, $other, ['name' => 'Other', 'sku' => 'O', 'kind' => 'retail', 'priceMinor' => 1, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
        $this->postJson($url, ['lines' => [[...$line, 'productId' => $foreign->id]], 'payment' => null])->assertNotFound();
    }

    public function test_mixed_basket_quote_uses_aggregate_recipe_effects_and_current_recipes(): void
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
        $before = InventoryItem::all()->toArray();
        $url = '/'.$store->slug.'/pos/quotes';
        $quote = $this->actingAs($u)->postJson($url, ['lines' => $lines, 'payment' => null])->assertOk()->assertJsonPath('totalMinor', 41000)->assertJsonCount(0, 'blockingIssues')->assertJsonCount(3, 'warnings')->json();
        $effects = collect($quote['stockEffects'])->keyBy('inventoryItemId');
        foreach (['Beef' => '-100.000', 'Sauce' => '-35.000', 'Seasoning' => '0.625', 'Packaging' => '-1.000'] as $name => $after) {
            $this->assertSame($after, $effects[$ids[$name]]['after']);
        }
        $this->assertSame('1.000', $effects[$water->stock_item_id]['after']);
        $this->assertSame('1.000', $effects[$cheese->stock_item_id]['after']);
        $burger->components()->where('inventory_item_id', $ids['Beef'])->update(['quantity_thousandths' => 110000]);
        $next = $this->postJson($url, ['lines' => $lines, 'payment' => null])->assertOk()->json();
        $effect = collect($next['stockEffects'])->firstWhere('inventoryItemId', $ids['Beef']);
        $this->assertSame('380.000', $effect['demand']);
        $this->assertSame('-130.000', $effect['after']);
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertDatabaseCount('stock_movements', 0);
        foreach ([[], false, 0, 'cash'] as $payment) {
            $this->postJson($url, ['lines' => $lines, 'payment' => $payment])->assertUnprocessable()->assertJsonStructure(['error' => ['fieldErrors']]);
        }
    }
}
