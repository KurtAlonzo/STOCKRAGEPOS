<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_runtime_price_type_and_stock_precision_validation(): void
    {
        $user = User::factory()->create();
        $base = '/'.$user->currentTeam->slug.'/products';
        $input = ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'categoryId' => null, 'active' => true, 'priceMinor' => 100, 'allowedAddonIds' => [], 'lowStockThreshold' => null];
        foreach ([-1, 1.5, '9007199254740992'] as $price) {
            $this->actingAs($user)->post($base, [...$input, 'priceMinor' => $price])->assertSessionHasErrors('priceMinor');
        }
        $this->post($base, [...$input, 'lowStockThreshold' => '0.5'])->assertSessionHasErrors('lowStockThreshold');
        $this->post($base, $input)->assertSessionHasNoErrors();
        $product = CatalogItem::firstOrFail();
        unset($input['lowStockThreshold']);
        $this->patch($base.'/'.$product->id, [...$input, 'kind' => 'food', 'components' => [], 'active' => false, 'expectedRevision' => '1'])->assertSessionHasErrors('kind');
        $this->patch($base.'/'.$product->id, [...$input, 'expectedRevision' => '0'])->assertSessionHasErrors('expectedRevision');
        $this->assertDatabaseCount('catalog_items', 1);
        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_catalog_page_batches_stock_and_recipe_queries(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $stock = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Water '.$i, 'kind' => 'retail', 'unit' => 'each']);
            CatalogItem::create(['team_id' => $user->current_team_id, 'name' => 'Water '.$i, 'sku' => 'W-'.$i, 'kind' => 'retail', 'price_minor' => 100, 'stock_item_id' => $stock->id]);
        }
        DB::enableQueryLog();
        $this->actingAs($user)->get('/'.$user->currentTeam->slug.'/products')->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/inventory_items|recipe_components|catalog_allowed_addons|catalog_items/', $q['query']));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(12, $queries->count());
    }

    public function test_retail_creation_and_sku_uniqueness(): void
    {
        $user = User::factory()->create();
        $base = '/'.$user->currentTeam->slug.'/products';
        $input = ['name' => 'Water', 'sku' => 'water-1', 'kind' => 'retail', 'categoryId' => null, 'active' => true, 'priceMinor' => 5000, 'allowedAddonIds' => [], 'lowStockThreshold' => '2'];
        $this->actingAs($user)->post($base, $input)->assertSessionHasNoErrors()->assertRedirect();
        $product = CatalogItem::firstOrFail();
        $this->assertSame('WATER-1', $product->sku);
        $this->assertSame(0, InventoryItem::findOrFail($product->stock_item_id)->balance_thousandths);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->post($base, $input)->assertSessionHasErrors('sku');
        $this->get($base)->assertOk();
        $this->get($base.'/'.$product->id.'/edit')->assertOk();
    }

    public function test_forged_stock_and_foreign_category_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::create(['team_id' => $other->current_team_id, 'name' => 'Other', 'name_key' => 'other']);
        $input = ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'categoryId' => $category->id, 'active' => true, 'priceMinor' => 5000, 'allowedAddonIds' => [], 'lowStockThreshold' => null];
        $this->actingAs($user)->post('/'.$user->currentTeam->slug.'/products', $input)->assertNotFound();
        $input['categoryId'] = null;
        $input['stockItemId'] = 123;
        $this->post('/'.$user->currentTeam->slug.'/products', $input)->assertSessionHasErrors('stockItemId');
        $this->assertDatabaseCount('catalog_items', 0);
    }
}
