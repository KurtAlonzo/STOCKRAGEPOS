<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_recipe_persists_exact_components_and_protects_dependencies(): void
    {
        $u = User::factory()->create();
        $stock = InventoryItem::create(['team_id' => $u->current_team_id, 'name' => 'Seasoning', 'kind' => 'ingredient', 'unit' => 'g']);
        $base = '/'.$u->currentTeam->slug;
        $input = ['name' => 'Burger', 'sku' => 'BURGER', 'kind' => 'food', 'categoryId' => null, 'active' => true, 'priceMinor' => 10000, 'allowedAddonIds' => [], 'components' => [['inventoryItemId' => $stock->id, 'unit' => 'g', 'quantity' => '0.125']]];
        $this->actingAs($u)->post($base.'/products', $input)->assertSessionHasNoErrors();
        $product = CatalogItem::firstOrFail();
        $this->assertNull($product->stock_item_id);
        $this->assertDatabaseHas('recipe_components', ['catalog_item_id' => $product->id, 'quantity_thousandths' => 125]);
        $this->patch($base.'/inventory/items/'.$stock->id, ['name' => 'Seasoning', 'active' => false, 'lowStockThreshold' => null, 'expectedRevision' => '1'])->assertSessionHasErrors('active');
        $this->patch($base.'/products/'.$product->id, [...$input, 'active' => false, 'expectedRevision' => '1'])->assertSessionHasNoErrors();
        $this->patch($base.'/inventory/items/'.$stock->id, ['name' => 'Seasoning', 'active' => false, 'lowStockThreshold' => null, 'expectedRevision' => '1'])->assertSessionHasNoErrors();
        $this->patch($base.'/products/'.$product->id, [...$input, 'active' => true, 'expectedRevision' => '2'])->assertSessionHasErrors('components.0.inventoryItemId');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_duplicate_components_and_stale_recipe_save_reject(): void
    {
        $u = User::factory()->create();
        $stock = InventoryItem::create(['team_id' => $u->current_team_id, 'name' => 'Flour', 'kind' => 'ingredient', 'unit' => 'g']);
        $base = '/'.$u->currentTeam->slug;
        $component = ['inventoryItemId' => $stock->id, 'unit' => 'g', 'quantity' => '1'];
        $input = ['name' => 'Bread', 'sku' => 'B', 'kind' => 'food', 'categoryId' => null, 'active' => true, 'priceMinor' => 100, 'allowedAddonIds' => [], 'components' => [$component, $component]];
        $this->actingAs($u)->post($base.'/products', $input)->assertSessionHasErrors('components.1.inventoryItemId');
        $input['components'] = [$component];
        $this->post($base.'/products', $input)->assertSessionHasNoErrors();
        $p = CatalogItem::firstOrFail();
        $this->patch($base.'/products/'.$p->id.'/recipe', ['components' => [$component], 'expectedRevision' => '0'])->assertSessionHasErrors('expectedRevision');
    }
}
