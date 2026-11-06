<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_uniqueness_revision_and_active_dependents(): void
    {
        $u = User::factory()->create();
        $base = '/'.$u->currentTeam->slug;
        $this->actingAs($u)->post($base.'/categories', ['name' => ' Drinks ', 'active' => true])->assertSessionHasNoErrors();
        $c = Category::firstOrFail();
        $this->post($base.'/categories', ['name' => 'drinks', 'active' => true])->assertSessionHasErrors('name');
        $this->patch($base.'/categories/'.$c->id, ['name' => 'Other', 'active' => true, 'expectedRevision' => '0'])->assertSessionHasErrors('expectedRevision');
        $this->post($base.'/products', ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'categoryId' => $c->id, 'active' => true, 'priceMinor' => 0, 'allowedAddonIds' => [], 'lowStockThreshold' => null])->assertSessionHasNoErrors();
        $this->patch($base.'/categories/'.$c->id, ['name' => 'Drinks', 'active' => false, 'expectedRevision' => '1'])->assertSessionHasErrors('active');
        $this->assertTrue($c->fresh()->active);
        $this->assertSame(0, CatalogItem::firstOrFail()->price_minor);
    }
}
