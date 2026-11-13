<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAndVoidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function sell(Product $p, int $qty, $at = null): Sale
    {
        return app(SaleService::class)->checkout([['product_id' => $p->id, 'qty' => $qty]], 10000, 'cash', $at);
    }

    public function test_voiding_returns_stock_logs_a_void_and_excludes_the_sale_from_totals(): void
    {
        $p = Product::factory()->create(['name' => 'Classic Takoyaki', 'price' => 50, 'stock' => 100]);
        $sale = $this->sell($p, 5);
        $this->assertSame(95, $p->fresh()->stock);

        $this->get('/dashboard')->assertSee('250.00');

        $this->post("/sales/{$sale->id}/void")->assertRedirect("/sales/{$sale->id}")->assertSessionHas('success');

        $this->assertSame(100, $p->fresh()->stock);
        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'void', 'qty_change' => 5, 'previous_qty' => 95, 'new_qty' => 100, 'reason' => 'Void Sale #'.$sale->id]);

        // not counted on dashboard / reports
        $this->assertSame(0, Sale::completed()->count());
        $this->get('/dashboard')->assertDontSee('250.00');
        $this->get('/reports?period=daily')->assertDontSee('250.00');
    }

    public function test_a_sale_cannot_be_voided_twice(): void
    {
        $p = Product::factory()->create(['stock' => 100]);
        $sale = $this->sell($p, 5);

        $this->post("/sales/{$sale->id}/void");
        $this->post("/sales/{$sale->id}/void")->assertSessionHas('error');

        $this->assertSame(100, $p->fresh()->stock); // not 105
        $this->assertSame(1, \App\Models\StockMovement::where('type', 'void')->count());
    }

    public function test_sales_history_search_and_date_filters(): void
    {
        $p = Product::factory()->create(['stock' => 1000, 'name' => 'Cheese Takoyaki']);
        $old = $this->sell($p, 1, now()->subDays(10));
        $mid = $this->sell($p, 2, now()->subDays(5));
        $new = $this->sell($p, 3);

        // by id (also with a leading #)
        $this->get('/sales?search='.$mid->id)->assertOk()->assertSee('#'.$mid->id)->assertDontSee('#'.$old->id.'<', false);
        $res = $this->get('/sales?search=%23'.$new->id);
        $this->assertCount(1, $res->viewData('sales'));
        $this->assertSame($new->id, $res->viewData('sales')->first()->id);

        // date range
        $res = $this->get('/sales?from='.now()->subDays(6)->toDateString().'&to='.now()->subDays(4)->toDateString());
        $this->assertSame([$mid->id], $res->viewData('sales')->pluck('id')->all());

        $res = $this->get('/sales?from='.now()->subDays(11)->toDateString().'&to='.now()->subDays(9)->toDateString());
        $this->assertSame([$old->id], $res->viewData('sales')->pluck('id')->all());

        // filters stay when paging
        $this->assertStringContainsString('from=', $this->get('/sales?from='.now()->subDays(30)->toDateString())->viewData('sales')->url(2));
    }

    public function test_sales_pagination_keeps_filters(): void
    {
        $p = Product::factory()->create(['stock' => 1000]);
        for ($i = 0; $i < 17; $i++) { $this->sell($p, 1); }

        $res = $this->get('/sales?status=completed')->assertOk();
        $this->assertCount(15, $res->viewData('sales'));
        $this->assertStringContainsString('status=completed', $res->viewData('sales')->nextPageUrl());
        $this->get('/sales?status=completed&page=2')->assertOk();
    }

    public function test_sale_details_page_lists_items_and_void_button(): void
    {
        $p = Product::factory()->create(['name' => 'Octobits', 'price' => 70, 'stock' => 50]);
        $sale = $this->sell($p, 3);

        $this->get("/sales/{$sale->id}")->assertOk()->assertSee('Octobits')->assertSee('210.00')->assertSee('Void this sale');

        $this->post("/sales/{$sale->id}/void");
        $this->get("/sales/{$sale->id}")->assertOk()->assertSee('This sale was voided')->assertDontSee('Void this sale');
    }

    public function test_stock_history_shows_sale_rows_and_filters(): void
    {
        $a = Product::factory()->create(['name' => 'Classic Takoyaki', 'stock' => 100]);
        $b = Product::factory()->create(['name' => 'Cheese Takoyaki', 'stock' => 100]);
        $sale = $this->sell($a, 5);
        $this->sell($b, 1);

        $this->get('/stock-history')->assertOk()->assertSee('Classic Takoyaki')->assertSee('-5')->assertSee('Sale #'.$sale->id);

        $res = $this->get('/stock-history?product_id='.$a->id);
        $this->assertSame([$a->id], $res->viewData('movements')->pluck('product_id')->unique()->values()->all());

        $res = $this->get('/stock-history?type=void');
        $this->assertCount(0, $res->viewData('movements'));
        $this->post("/sales/{$sale->id}/void");
        $this->assertCount(1, $this->get('/stock-history?type=void')->viewData('movements'));

        $this->get('/stock-history?type=nonsense')->assertSessionHasErrors('type');
    }
}
