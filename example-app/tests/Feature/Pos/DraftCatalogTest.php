<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_off_page_and_inactive_items_resolve_without_exposing_foreign_records(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        $save = new SaveCatalogItem;
        $base = ['kind' => 'retail', 'priceMinor' => 5000, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null];
        $selected = null;
        for ($i = 0; $i < 27; $i++) {
            $selected = $save->handle($u->currentTeam, $u, [...$base, 'name' => 'Water '.$i, 'sku' => 'W'.$i]);
        }
        $selected->update(['active' => false]);
        $foreign = $save->handle($other->currentTeam, $other, [...$base, 'name' => 'Secret foreign name', 'sku' => 'SECRET']);
        $lines = [['id' => 'saved', 'productId' => $selected->id, 'quantity' => 1, 'addonIds' => []], ['id' => 'foreign', 'productId' => $foreign->id, 'quantity' => 1, 'addonIds' => []], ['id' => 'missing', 'productId' => 99999, 'quantity' => 1, 'addonIds' => []]];
        $response = $this->actingAs($u)->withHeader('X-POS-Actor-Id', (string) $u->id)->postJson('/'.$u->currentTeam->slug.'/pos/draft-catalog', ['lines' => $lines])->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $selected->id)->assertJsonPath('products.0.active', false)->assertJsonCount(3, 'issues');
        $this->assertStringNotContainsString('Secret foreign name', $response->getContent());
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
