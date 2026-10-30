<?php

namespace Tests\Feature\Inventory;

use App\Data\InventoryData;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_state_precedence_and_metadata_do_not_change_balances(): void
    {
        $item = new InventoryItem(['kind' => 'ingredient', 'unit' => 'g', 'threshold_thousandths' => 2000, 'balance_thousandths' => 2000]);
        $this->assertSame('low', InventoryData::item($item)['stockState']);
        $item->balance_thousandths = 0;
        $this->assertSame('zero', InventoryData::item($item)['stockState']);
        $item->balance_thousandths = -1;
        $this->assertSame('negative', InventoryData::item($item)['stockState']);
        $item->balance_thousandths = 2000;
        $item->threshold_thousandths = null;
        $this->assertSame('positive', InventoryData::item($item)['stockState']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_create_filter_and_edit_inventory(): void
    {
        $user = User::factory()->create();
        $base = '/'.$user->currentTeam->slug.'/inventory';
        $this->actingAs($user)->post($base.'/items', ['name' => 'Beef', 'kind' => 'ingredient', 'unit' => 'g', 'active' => true, 'lowStockThreshold' => '250'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('inventory_items', ['name' => 'Beef', 'balance_thousandths' => 0, 'threshold_thousandths' => 250000]);
        $item = InventoryItem::firstOrFail();
        $this->get($base)->assertOk();
        $this->patch($base.'/items/'.$item->id, ['name' => 'Beef mince', 'active' => true, 'lowStockThreshold' => null, 'expectedRevision' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Beef mince', $item->fresh()->name);
        $this->patch($base.'/items/'.$item->id, ['name' => 'Old edit', 'active' => true, 'lowStockThreshold' => null, 'expectedRevision' => '1'])->assertSessionHasErrors('expectedRevision');
    }

    public function test_foreign_items_and_nonowners_are_denied(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/'.$owner->currentTeam->slug.'/inventory')->assertForbidden();
        $this->actingAs($owner)->post('/'.$owner->currentTeam->slug.'/inventory/items', ['name' => 'Salt', 'kind' => 'ingredient', 'unit' => 'g', 'active' => true, 'lowStockThreshold' => null]);
        $item = InventoryItem::firstOrFail();
        $this->actingAs($other)->patch('/'.$other->currentTeam->slug.'/inventory/items/'.$item->id, [])->assertNotFound();
    }
}
