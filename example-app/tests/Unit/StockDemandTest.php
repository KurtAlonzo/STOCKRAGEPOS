<?php

namespace Tests\Unit;

use App\Actions\Catalog\SaveCatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Catalog\StockDemand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDemandTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_basket_aggregates_without_mutating_stock(): void
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
        $effects = collect((new StockDemand)->calculate($store, [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $water->id, 'quantity' => 1, 'addonIds' => []], ['id' => '22222222-2222-4222-8222-222222222222', 'productId' => $burger->id, 'quantity' => 2, 'addonIds' => [$cheese->id]], ['id' => '33333333-3333-4333-8333-333333333333', 'productId' => $burger->id, 'quantity' => 1, 'addonIds' => [$extra->id]]]))->keyBy('inventoryItemId');
        $this->assertSame('350.000', $effects[$ids['Beef']]['demand']);
        $this->assertSame('-100.000', $effects[$ids['Beef']]['after']);
        $this->assertSame('-35.000', $effects[$ids['Sauce']]['after']);
        $this->assertSame('0.625', $effects[$ids['Seasoning']]['after']);
        $this->assertSame('-1.000', $effects[$ids['Packaging']]['after']);
        $this->assertSame('1.000', $effects[$water->stock_item_id]['after']);
        $this->assertSame('1.000', $effects[$cheese->stock_item_id]['after']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(250000, InventoryItem::findOrFail($ids['Beef'])->balance_thousandths);
    }
}
