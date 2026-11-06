<?php

namespace Tests\Feature;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Task3AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_references_cannot_be_used_under_an_owned_store(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $base = '/'.$owner->currentTeam->slug;
        $foreign = InventoryItem::create(['team_id' => $other->current_team_id, 'name' => 'Foreign flour', 'kind' => 'ingredient', 'unit' => 'g']);
        $product = CatalogItem::create(['team_id' => $other->current_team_id, 'name' => 'Foreign addon', 'sku' => 'F', 'kind' => 'addon', 'stock_behavior' => 'recipe', 'price_minor' => 100]);
        $category = Category::create(['team_id' => $other->current_team_id, 'name' => 'Foreign', 'name_key' => 'foreign']);
        $this->actingAs($owner)->get($base.'/products/'.$product->id.'/edit')->assertNotFound();
        $this->patch($base.'/products/'.$product->id, [])->assertNotFound();
        $this->patch($base.'/products/'.$product->id.'/recipe', [])->assertNotFound();
        $this->patch($base.'/categories/'.$category->id, [])->assertNotFound();
        $this->get($base.'/products?categoryId='.$category->id)->assertNotFound();
        $input = ['name' => 'Local', 'sku' => 'L', 'kind' => 'food', 'priceMinor' => 100, 'active' => true, 'categoryId' => null, 'allowedAddonIds' => [], 'components' => [['inventoryItemId' => $foreign->id, 'quantity' => '1', 'unit' => 'g']]];
        $this->post($base.'/products', $input)->assertNotFound();
        unset($input['components']);
        $input['kind'] = 'retail';
        $input['lowStockThreshold'] = null;
        $input['allowedAddonIds'] = [$product->id];
        $this->post($base.'/products', $input)->assertNotFound();
        $this->assertDatabaseCount('catalog_items', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $oldStore = $owner->currentTeam;
        app(CreateTeam::class)->handle($owner, 'Another owned store');
        $this->get($base.'/inventory')->assertOk();
        $this->assertSame($oldStore->id, $owner->fresh()->current_team_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_business_endpoint_access_matrix(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $slug = $owner->currentTeam->slug;
        $stock = InventoryItem::create(['team_id' => $owner->current_team_id, 'name' => 'Salt', 'kind' => 'ingredient', 'unit' => 'g']);
        $paths = [['get', 'inventory'], ['post', 'inventory/items'], ['patch', 'inventory/items/'.$stock->id], ['post', 'inventory/receipts'], ['post', 'inventory/adjustments'], ['get', 'products'], ['get', 'products/create'], ['post', 'products'], ['get', 'products/1/edit'], ['patch', 'products/1'], ['patch', 'products/1/recipe'], ['get', 'categories'], ['post', 'categories'], ['patch', 'categories/1']];
        foreach ($paths as [$method,$path]) {
            $this->$method('/'.$slug.'/'.$path)->assertRedirect('/login');
        }
        foreach ([TeamRole::Admin, TeamRole::Member] as $role) {
            $owner->currentTeam->members()->syncWithoutDetaching([$other->id => ['role' => $role->value]]);
            $before = $other->fresh()->current_team_id;
            $this->actingAs($other);
            foreach ($paths as [$method,$path]) {
                $this->$method('/'.$slug.'/'.$path)->assertForbidden();
            }$this->assertSame($before, $other->fresh()->current_team_id);
        }
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/'.$unverified->currentTeam->slug.'/inventory')->assertRedirect(route('verification.notice'));
        $this->actingAs($other)->get('/'.$other->currentTeam->slug.'/inventory?itemId='.$stock->id)->assertNotFound();
        $this->post('/'.$other->currentTeam->slug.'/inventory/receipts', ['inventoryItemId' => $stock->id, 'quantity' => '1', 'reason' => 'Bad', 'expectedRevision' => '1'])->assertNotFound();
    }
}
