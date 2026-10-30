<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\RecordStockOperation;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_failure_rolls_back_balance_revision_and_operation(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Flour', 'kind' => 'ingredient', 'unit' => 'g']);
        Event::listen('eloquent.creating: App\\Models\\StockMovement', fn () => throw new \RuntimeException('Simulated movement failure'));
        try {
            try {
                (new RecordStockOperation)->handle($user->currentTeam, $user, $item, 'receipt', '1', 'Opening', '1');
                $this->fail('Movement should fail.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Simulated movement failure', $error->getMessage());
            }
            $this->assertSame(0, $item->fresh()->balance_thousandths);
            $this->assertSame(1, $item->fresh()->revision);
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        } finally {
            Event::forget('eloquent.creating: App\\Models\\StockMovement');
        }
    }

    public function test_movement_snapshots_survive_metadata_changes_and_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Flour', 'kind' => 'ingredient', 'unit' => 'g']);
        $movement = (new RecordStockOperation)->handle($user->currentTeam, $user, $item, 'receipt', '2', 'Opening', '1');
        $item->update(['name' => 'Renamed flour']);
        $user->update(['name' => 'Renamed owner']);
        $this->assertSame('Flour', $movement->fresh()->item_name);
        $this->assertNotSame('Renamed owner', $movement->fresh()->actor_name);
        $this->expectException(\LogicException::class);
        $movement->update(['reason' => 'Rewrite history']);
    }

    public function test_receipt_replay_and_correction_are_atomic(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Beef', 'kind' => 'ingredient', 'unit' => 'g']);
        $base = '/'.$user->currentTeam->slug.'/inventory';
        $input = ['inventoryItemId' => $item->id, 'quantity' => '250.000', 'reason' => 'Opening stock', 'expectedRevision' => '1'];
        $this->actingAs($user)->post($base.'/receipts', $input)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(250000, $item->fresh()->balance_thousandths);
        $this->post($base.'/receipts', $input)->assertSessionHasErrors('expectedRevision');
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('stock_operations', 1);
        $this->post($base.'/adjustments', ['inventoryItemId' => $item->id, 'delta' => '-255', 'reason' => 'Physical count correction', 'expectedRevision' => '2'])->assertSessionHasNoErrors();
        $this->assertSame(-5000, $item->fresh()->balance_thousandths);
        $this->assertDatabaseHas('stock_movements', ['before_thousandths' => 250000, 'delta_thousandths' => -255000, 'after_thousandths' => -5000, 'actor_id' => $user->id]);
    }

    public function test_direct_negative_stock_and_invalid_receipts_do_not_write(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Water', 'kind' => 'retail', 'unit' => 'each']);
        $base = '/'.$user->currentTeam->slug.'/inventory';
        $this->actingAs($user)->post($base.'/adjustments', ['inventoryItemId' => $item->id, 'delta' => '-1', 'reason' => 'Count', 'expectedRevision' => '1'])->assertSessionHasErrors('delta');
        $this->post($base.'/receipts', ['inventoryItemId' => $item->id, 'quantity' => '0.5', 'reason' => 'Receipt', 'expectedRevision' => '1'])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $item->fresh()->balance_thousandths);
    }
}
