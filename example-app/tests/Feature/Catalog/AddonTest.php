<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_addons_and_active_assignment_guard(): void
    {
        $u = User::factory()->create();
        $base = '/'.$u->currentTeam->slug.'/products';
        $input = ['name' => 'Cheese', 'sku' => 'CH', 'kind' => 'addon', 'stockBehavior' => 'direct', 'categoryId' => null, 'active' => true, 'priceMinor' => 1500, 'allowedAddonIds' => [], 'lowStockThreshold' => null];
        $this->actingAs($u)->post($base, $input)->assertSessionHasNoErrors();
        $addon = CatalogItem::firstOrFail();
        $this->assertSame('addon', InventoryItem::findOrFail($addon->stock_item_id)->kind);
        $this->post($base, ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'categoryId' => null, 'active' => true, 'priceMinor' => 5000, 'allowedAddonIds' => [$addon->id], 'lowStockThreshold' => null])->assertSessionHasNoErrors();
        unset($input['lowStockThreshold']);
        $this->patch($base.'/'.$addon->id, [...$input, 'active' => false, 'expectedRevision' => '1'])->assertSessionHasErrors('active');
        $this->assertTrue($addon->fresh()->active);
    }
}
