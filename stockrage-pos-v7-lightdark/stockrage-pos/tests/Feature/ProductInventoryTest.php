<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_add_product_with_starting_stock_records_an_in_movement(): void
    {
        Storage::fake('public');

        $this->post('/products', [
            'name' => 'Classic Takoyaki', 'category' => 'Takoyaki', 'price' => '50.00',
            'stock' => 100, 'low_stock_threshold' => 20, 'is_active' => 1,
            'image' => UploadedFile::fake()->image('tako.jpg'),
        ])->assertRedirect('/products');

        $product = Product::firstWhere('name', 'Classic Takoyaki');
        $this->assertSame(100, $product->stock);
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'in', 'qty_change' => 100, 'previous_qty' => 0, 'new_qty' => 100,
        ]);
    }

    public function test_product_validation(): void
    {
        $this->post('/products', ['name' => '', 'price' => -1, 'stock' => -5, 'low_stock_threshold' => 'x'])
            ->assertSessionHasErrors(['name', 'price', 'stock', 'low_stock_threshold']);

        $this->post('/products', [
            'name' => 'Big image', 'price' => 10, 'stock' => 0, 'low_stock_threshold' => 1,
            'image' => UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg'),
        ])->assertSessionHasErrors('image');
    }

    public function test_edit_updates_details_but_cannot_change_stock_directly(): void
    {
        $p = Product::factory()->create(['stock' => 40]);

        $this->put("/products/{$p->id}", ['name' => 'Renamed', 'price' => 99, 'low_stock_threshold' => 5, 'is_active' => 1])
            ->assertRedirect('/products');
        $this->assertSame('Renamed', $p->fresh()->name);
        $this->assertSame(40, $p->fresh()->stock);

        $this->put("/products/{$p->id}", ['name' => 'Renamed', 'price' => 99, 'low_stock_threshold' => 5, 'stock' => 999])
            ->assertSessionHasErrors('stock');
        $this->assertSame(40, $p->fresh()->stock);
    }

    public function test_add_stock_and_adjust_stock_are_recorded(): void
    {
        $p = Product::factory()->create(['stock' => 10]);

        $this->post("/products/{$p->id}/add-stock", ['quantity' => 15])->assertSessionHasNoErrors();
        $this->assertSame(25, $p->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'in', 'qty_change' => 15, 'previous_qty' => 10, 'new_qty' => 25]);

        // reason is required for adjustments
        $this->post("/products/{$p->id}/adjust", ['new_quantity' => 20])->assertSessionHasErrors('reason');
        $this->assertSame(25, $p->fresh()->stock);

        $this->post("/products/{$p->id}/adjust", ['new_quantity' => 20, 'reason' => 'Spoiled'])->assertSessionHasNoErrors();
        $this->assertSame(20, $p->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'adjustment', 'qty_change' => -5, 'previous_qty' => 25, 'new_qty' => 20, 'reason' => 'Spoiled']);

        // unchanged quantity does nothing
        $before = StockMovement::count();
        $this->post("/products/{$p->id}/adjust", ['new_quantity' => 20, 'reason' => 'Same'])->assertSessionHas('error');
        $this->assertSame($before, StockMovement::count());
    }

    public function test_status_badges_follow_the_threshold(): void
    {
        $p = Product::factory()->create(['stock' => 50, 'low_stock_threshold' => 20]);
        $this->assertSame('ok', $p->status);

        $p->update(['stock' => 20]);
        $this->assertSame('low', $p->fresh()->status);

        $p->update(['stock' => 0]);
        $this->assertSame('out', $p->fresh()->status);

        $this->get('/products')->assertSee('Out of stock');
        $p->update(['stock' => 5]);
        $this->get('/products')->assertSee('Low stock');
    }

    public function test_toggle_active_and_delete_rules(): void
    {
        $p = Product::factory()->create();

        $this->patch("/products/{$p->id}/toggle")->assertSessionHas('success');
        $this->assertFalse($p->fresh()->is_active);
        $this->patch("/products/{$p->id}/toggle");
        $this->assertTrue($p->fresh()->is_active);

        $this->delete("/products/{$p->id}")->assertRedirect('/products');
        $this->assertNull(Product::find($p->id));
    }

    public function test_product_with_sales_history_cannot_be_deleted(): void
    {
        $p = Product::factory()->create();
        app(\App\Services\SaleService::class)->checkout([['product_id' => $p->id, 'qty' => 1]], 1000, 'cash');

        $this->delete("/products/{$p->id}")->assertSessionHas('error');
        $this->assertNotNull(Product::find($p->id));
    }

    public function test_product_list_filters(): void
    {
        Product::factory()->create(['name' => 'Alpha Cheese', 'stock' => 100]);
        Product::factory()->create(['name' => 'Beta Crab', 'stock' => 3, 'low_stock_threshold' => 10]);
        Product::factory()->create(['name' => 'Gamma Out', 'stock' => 0]);
        Product::factory()->create(['name' => 'Delta Hidden', 'is_active' => false]);

        $this->get('/products?q=cheese')->assertSee('Alpha Cheese')->assertDontSee('Beta Crab');
        $this->get('/products?status=low')->assertSee('Beta Crab')->assertDontSee('Alpha Cheese')->assertDontSee('Gamma Out');
        $this->get('/products?status=out')->assertSee('Gamma Out')->assertDontSee('Beta Crab');
        $this->get('/products?status=inactive')->assertSee('Delta Hidden')->assertDontSee('Alpha Cheese');
    }
}
