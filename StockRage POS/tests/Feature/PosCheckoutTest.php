<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function classic(array $o = []): Product
    {
        return Product::factory()->create($o + ['name' => 'Classic Takoyaki', 'price' => 50, 'stock' => 100, 'low_stock_threshold' => 20]);
    }

    public function test_selling_5_classic_takoyaki_drops_stock_from_100_to_95(): void
    {
        $p = $this->classic();

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => 5]], 'amount_paid' => 300, 'payment_method' => 'cash',
        ])->assertOk()->assertJson(['ok' => true, 'change' => 50, 'total' => 250, 'stocks' => [(string) $p->id => 95]]);

        $this->assertSame(95, $p->fresh()->stock);
        $sale = Sale::first();
        $this->assertSame('250.00', $sale->total);
        $this->assertSame('50.00', $sale->change_amount);
        $this->assertSame('completed', $sale->status);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $p->id, 'qty' => 5, 'price' => '50.00']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'sale', 'qty_change' => -5, 'previous_qty' => 100, 'new_qty' => 95, 'reason' => 'Sale #'.$sale->id]);
    }

    public function test_server_ignores_a_tampered_total_and_uses_database_prices(): void
    {
        $p = $this->classic(['price' => 50]);

        // Browser claims it is only 1 peso each; payment of 10 must be rejected by the real price.
        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => 2, 'price' => 1]], 'total' => 2, 'amount_paid' => 10, 'payment_method' => 'cash',
        ])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'Payment is not enough']);

        $this->assertSame(0, Sale::count());
        $this->assertSame(100, $p->fresh()->stock);
    }

    public function test_payment_that_is_too_small_is_rejected_and_nothing_is_saved(): void
    {
        $p = $this->classic();

        $this->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 5]], 'amount_paid' => 249.99, 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonPath('message', 'Payment is not enough');

        $this->assertSame(100, $p->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
    }

    public function test_cannot_oversell_and_whole_transaction_rolls_back(): void
    {
        $a = $this->classic();
        $b = Product::factory()->create(['name' => 'Cheese', 'price' => 60, 'stock' => 3]);

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $a->id, 'qty' => 10], ['product_id' => $b->id, 'qty' => 4]],
            'amount_paid' => 5000, 'payment_method' => 'cash',
        ])->assertStatus(422)->assertJsonPath('message', 'Not enough stock for Cheese (only 3 left)');

        // First item must NOT have been deducted: cancelled orders deduct nothing.
        $this->assertSame(100, $a->fresh()->stock);
        $this->assertSame(3, $b->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_inactive_products_cannot_be_sold(): void
    {
        $p = $this->classic(['is_active' => false]);

        $this->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 1]], 'amount_paid' => 100, 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonPath('message', 'Classic Takoyaki is not available');
        $this->assertSame(100, $p->fresh()->stock);
    }

    public function test_validation_errors(): void
    {
        $p = $this->classic();

        $this->postJson('/pos/checkout', ['items' => [], 'amount_paid' => 10, 'payment_method' => 'cash'])->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/pos/checkout', ['items' => [['product_id' => 9999, 'qty' => 1]], 'amount_paid' => 10, 'payment_method' => 'cash'])->assertJsonValidationErrors('items.0.product_id');
        $this->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 0]], 'amount_paid' => 10, 'payment_method' => 'cash'])->assertJsonValidationErrors('items.0.qty');
        $this->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 1]], 'amount_paid' => 'abc', 'payment_method' => 'bitcoin'])->assertJsonValidationErrors(['amount_paid', 'payment_method']);
    }

    public function test_same_product_twice_is_merged_into_one_line(): void
    {
        $p = $this->classic();

        $this->postJson('/pos/checkout', ['items' => [['product_id' => $p->id, 'qty' => 2], ['product_id' => $p->id, 'qty' => 3]], 'amount_paid' => 250, 'payment_method' => 'gcash'])->assertOk();

        $this->assertSame(1, Sale::first()->items()->count());
        $this->assertSame(95, $p->fresh()->stock);
    }

    public function test_pos_page_only_lists_active_products_and_shows_stock(): void
    {
        $this->classic();
        Product::factory()->create(['name' => 'Hidden One', 'is_active' => false]);
        Product::factory()->create(['name' => 'Sold Out One', 'stock' => 0]);

        $this->get('/pos')->assertOk()->assertSee('Classic Takoyaki')->assertSee('Sold Out One')->assertDontSee('Hidden One');
    }
}
