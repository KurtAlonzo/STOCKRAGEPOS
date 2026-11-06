<?php

namespace Tests\Unit;

use App\Actions\Catalog\SaveCatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Catalog\StockDemand;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StockDemandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demand_batches_repeated_selections_and_resolves_foreign_ids_before_local_state(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $save = new SaveCatalogItem;
        $input = ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 1, 'lowStockThreshold' => null];
        $product = $save->handle($user->currentTeam, $user, $input);
        $foreign = $save->handle($other->currentTeam, $other, $input);
        $lines = [];
        for ($i = 0; $i < 10; $i++) {
            $lines[] = ['id' => (string) Str::uuid(), 'productId' => $product->id, 'quantity' => 1, 'addonIds' => []];
        }
        DB::enableQueryLog();
        (new StockDemand)->calculate($user->currentTeam, $lines);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(7, count($queries));
        $product->update(['active' => false]);
        try {
            (new StockDemand)->calculate($user->currentTeam, [[...$lines[0], 'addonIds' => [$foreign->id]]]);
            $this->fail('Foreign resource accepted');
        } catch (ModelNotFoundException $error) {
            $this->assertNotEmpty($error->getModel());
        }
    }

    public function test_demand_rejects_overflow_at_each_calculation_stage(): void
    {
        $user = User::factory()->create();
        $stock = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Flour', 'kind' => 'ingredient', 'unit' => 'g']);
        $food = (new SaveCatalogItem)->handle($user->currentTeam, $user, ['name' => 'Bread', 'sku' => 'B', 'kind' => 'food', 'priceMinor' => 1, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'components' => [['inventoryItemId' => $stock->id, 'unit' => 'g', 'quantity' => '1']]]);
        $part = $food->components()->firstOrFail();
        foreach ([[9007199254740991, 0, 2, false], [4503599627370496, 0, 1, true], [1, -9007199254740991, 1, false]] as [$amount,$balance,$count,$twoLines]) {
            $part->update(['quantity_thousandths' => $amount]);
            $stock->update(['balance_thousandths' => $balance]);
            $line = ['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $food->id, 'quantity' => $count, 'addonIds' => []];
            $lines = $twoLines ? [$line, [...$line, 'id' => '22222222-2222-4222-8222-222222222222']] : [$line];
            $before = InventoryItem::all()->toArray();
            try {
                (new StockDemand)->calculate($user->currentTeam, $lines);
                $this->fail('Overflow accepted');
            } catch (ValidationException $error) {
                $this->assertContains('numeric_range_exceeded', array_merge(...array_values($error->errors())));
            }
            $this->assertSame($before, InventoryItem::all()->toArray());
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_demand_rejects_foreign_resources_and_inactive_dependencies(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $save = new SaveCatalogItem;
        $input = ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 1, 'lowStockThreshold' => null];
        $local = $save->handle($user->currentTeam, $user, $input);
        $foreign = $save->handle($other->currentTeam, $other, $input);
        $line = ['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $local->id, 'quantity' => 1, 'addonIds' => []];
        foreach ([[...$line, 'productId' => $foreign->id], [...$line, 'addonIds' => [$foreign->id]]] as $invalid) {
            try {
                (new StockDemand)->calculate($user->currentTeam, [$invalid]);
                $this->fail('Foreign resource accepted');
            } catch (ModelNotFoundException $error) {
                $this->assertNotEmpty($error->getModel());
            }
        }
        try {
            (new StockDemand)->forItem($user->currentTeam, $foreign);
            $this->fail('Foreign item accepted');
        } catch (HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
        }
        $addon = $save->handle($user->currentTeam, $user, [...$input, 'name' => 'Cheese', 'sku' => 'C', 'kind' => 'addon', 'stockBehavior' => 'direct']);
        foreach ([[...$line, 'productId' => $addon->id], [...$line, 'addonIds' => [$addon->id]]] as $invalid) {
            try {
                (new StockDemand)->calculate($user->currentTeam, [$invalid]);
                $this->fail('Invalid local selection accepted');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $local->addons()->attach($addon);
        $addon->update(['active' => false]);
        $before = InventoryItem::all()->toArray();
        try {
            (new StockDemand)->calculate($user->currentTeam, [[...$line, 'addonIds' => [$addon->id]]]);
            $this->fail('Inactive allowed add-on accepted for active parent');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('lines.0.addonIds', $error->errors());
        }
        $this->assertSame($before, InventoryItem::all()->toArray());
        $local->update(['active' => false]);
        foreach ([$line, [...$line, 'addonIds' => [$addon->id]]] as $invalid) {
            try {
                (new StockDemand)->calculate($user->currentTeam, [$invalid]);
                $this->fail('Inactive selection accepted');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $stock = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Salt', 'kind' => 'ingredient', 'unit' => 'g']);
        $food = $save->handle($user->currentTeam, $user, [...$input, 'name' => 'Food', 'sku' => 'F', 'kind' => 'food', 'components' => [['inventoryItemId' => $stock->id, 'unit' => 'g', 'quantity' => '1']]]);
        $stock->update(['active' => false]);
        try {
            (new StockDemand)->calculate($user->currentTeam, [[...$line, 'productId' => $food->id]]);
            $this->fail('Inactive dependency accepted');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertDatabaseCount('stock_operations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_direct_stock_boundaries_return_block_policy_without_writes(): void
    {
        $user = User::factory()->create();
        $item = (new SaveCatalogItem)->handle($user->currentTeam, $user, ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'lowStockThreshold' => null]);
        InventoryItem::whereKey($item->stock_item_id)->update(['balance_thousandths' => 2000]);
        $cheese = (new SaveCatalogItem)->handle($user->currentTeam, $user, ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'name' => 'Cheese', 'sku' => 'CH', 'kind' => 'addon', 'stockBehavior' => 'direct', 'priceMinor' => 1500, 'lowStockThreshold' => null]);
        InventoryItem::whereKey($cheese->stock_item_id)->update(['balance_thousandths' => 3000]);
        $item->addons()->attach($cheese);
        $before = InventoryItem::all()->toArray();
        foreach ([2 => '0.000', 3 => '-1.000'] as $count => $after) {
            $effects = (new StockDemand)->calculate($user->currentTeam, [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $item->id, 'quantity' => $count, 'addonIds' => []]]);
            $this->assertSame($after, $effects[0]['after']);
            $this->assertSame('block', $effects[0]['policy']);
        }
        foreach ([3 => '0.000', 4 => '-1.000'] as $count => $after) {
            $effects = collect((new StockDemand)->calculate($user->currentTeam, [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $item->id, 'quantity' => $count, 'addonIds' => [$cheese->id]]]))->keyBy('inventoryItemId');
            $this->assertSame($after, $effects[$cheese->stock_item_id]['after']);
            $this->assertSame('block', $effects[$cheese->stock_item_id]['policy']);
        }
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertDatabaseCount('stock_operations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_invalid_demand_lines_are_rejected_without_writes(): void
    {
        $user = User::factory()->create();
        $item = (new SaveCatalogItem)->handle($user->currentTeam, $user, ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'lowStockThreshold' => null]);
        $line = ['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $item->id, 'quantity' => 1, 'addonIds' => []];
        $before = InventoryItem::all()->toArray();
        foreach ([[...$line, 'quantity' => 0], [...$line, 'quantity' => -1], [...$line, 'quantity' => 0.5], [...$line, 'id' => 'invalid'], [...$line, 'addonIds' => [1, 1]]] as $invalid) {
            try {
                (new StockDemand)->calculate($user->currentTeam, [$invalid]);
                $this->fail('Invalid demand accepted');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
            $this->assertSame($before, InventoryItem::all()->toArray());
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
        try {
            (new StockDemand)->calculate($user->currentTeam, [$line, $line]);
            $this->fail('Duplicate UUID accepted');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertDatabaseCount('stock_operations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

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
        $before = InventoryItem::all()->toArray();
        $effects = collect((new StockDemand)->calculate($store, [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $water->id, 'quantity' => 1, 'addonIds' => []], ['id' => '22222222-2222-4222-8222-222222222222', 'productId' => $burger->id, 'quantity' => 2, 'addonIds' => [$cheese->id]], ['id' => '33333333-3333-4333-8333-333333333333', 'productId' => $burger->id, 'quantity' => 1, 'addonIds' => [$extra->id]]]))->keyBy('inventoryItemId');
        $this->assertSame('350.000', $effects[$ids['Beef']]['demand']);
        $this->assertSame('-100.000', $effects[$ids['Beef']]['after']);
        $this->assertSame('-35.000', $effects[$ids['Sauce']]['after']);
        $this->assertSame('0.625', $effects[$ids['Seasoning']]['after']);
        $this->assertSame('-1.000', $effects[$ids['Packaging']]['after']);
        $this->assertSame('1.000', $effects[$water->stock_item_id]['after']);
        $this->assertSame('1.000', $effects[$cheese->stock_item_id]['after']);
        $this->assertSame($before, InventoryItem::all()->toArray());
        $this->assertDatabaseCount('stock_operations', 0);
        foreach ($effects as $effect) {
            $this->assertSame(in_array($effect['inventoryItemId'], [$water->stock_item_id, $cheese->stock_item_id]) ? 'block' : 'warn', $effect['policy']);
        }
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(250000, InventoryItem::findOrFail($ids['Beef'])->balance_thousandths);
    }
}
