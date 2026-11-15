<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Version 3 upgrades: cashier name, void reason, receipts, cost price + profit, placeholder art, sidebar. */
class UpgradesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Kai Cashier']);
        $this->actingAs($this->admin);
    }

    private function sell(Product $p, int $qty, ?User $by = null): Sale
    {
        return app(SaleService::class)->checkout([['product_id' => $p->id, 'qty' => $qty]], 100000, 'cash', null, $by?->id);
    }

    public function test_checkout_records_the_cashier_and_copies_the_cost(): void
    {
        $p = Product::factory()->create(['price' => 60, 'cost_price' => 25, 'stock' => 100]);

        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $p->id, 'qty' => 2]],
            'amount_paid' => 200,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['ok' => true]);

        $sale = Sale::firstOrFail();
        $this->assertSame($this->admin->id, $sale->user_id);
        $this->assertEquals(25, $sale->items->first()->cost);

        // Changing the cost later must not rewrite old profit.
        $p->update(['cost_price' => 55]);
        $this->assertEquals(70, $sale->fresh('items')->profit);
    }

    public function test_profit_shows_in_reports_csv_and_dashboard_and_ignores_voided_sales(): void
    {
        $p = Product::factory()->create(['price' => 60, 'cost_price' => 25, 'stock' => 100]);
        $this->sell($p, 2, $this->admin);                         // profit (60-25)*2 = 70
        $voided = $this->sell($p, 10, $this->admin);              // would be 350, voided below
        app(SaleService::class)->void($voided);

        $report = $this->get('/reports?period=daily')->assertOk();
        $this->assertEquals(70, $report->viewData('totalProfit'));
        $this->assertEquals(120, $report->viewData('totalSales'));
        $report->assertSee('Profit');

        $this->assertEquals(70, $this->get('/dashboard')->assertOk()->viewData('monthlyProfit'));

        $csv = $this->get('/reports/export?period=daily')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Daily period",Orders,"Total sales",Profit', $csv);
        $this->assertStringContainsString('TOTAL,1,120.00,70.00', $csv);
    }

    public function test_cost_price_can_be_saved_and_defaults_to_zero(): void
    {
        $this->post('/products', ['name' => 'Costed', 'price' => 60, 'cost_price' => 27.5, 'stock' => 5, 'low_stock_threshold' => 2, 'is_active' => 1])
            ->assertRedirect('/products');
        $this->assertEquals(27.5, Product::where('name', 'Costed')->firstOrFail()->cost_price);

        $this->post('/products', ['name' => 'No cost', 'price' => 60, 'stock' => 0, 'low_stock_threshold' => 2, 'is_active' => 1])
            ->assertRedirect('/products');
        $this->assertEquals(0, Product::where('name', 'No cost')->firstOrFail()->cost_price);

        $this->post('/products', ['name' => 'Bad', 'price' => 60, 'cost_price' => -1, 'low_stock_threshold' => 2])
            ->assertSessionHasErrors('cost_price');

        $p = Product::where('name', 'Costed')->firstOrFail();
        $this->get("/products/{$p->id}/edit")->assertOk()->assertSee('Cost per piece');
    }

    public function test_void_saves_who_and_why_and_shows_it(): void
    {
        $p = Product::factory()->create(['price' => 50, 'stock' => 100]);
        $sale = $this->sell($p, 5, $this->admin);

        $this->post("/sales/{$sale->id}/void", ['reason' => 'Wrong order'])->assertRedirect("/sales/{$sale->id}");

        $sale->refresh();
        $this->assertSame('Wrong order', $sale->void_reason);
        $this->assertSame($this->admin->id, $sale->voided_by);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'void', 'reason' => "Void Sale #{$sale->id} - Wrong order"]);

        $this->get("/sales/{$sale->id}")->assertOk()
            ->assertSee('This sale was voided by Kai Cashier')
            ->assertSee('Wrong order');
    }

    public function test_void_reason_is_optional_and_limited(): void
    {
        $p = Product::factory()->create(['stock' => 100]);
        $a = $this->sell($p, 1);
        $this->post("/sales/{$a->id}/void")->assertRedirect();
        $this->assertNull($a->fresh()->void_reason);

        $b = $this->sell($p, 1);
        $this->post("/sales/{$b->id}/void", ['reason' => str_repeat('x', 201)])->assertSessionHasErrors('reason');
        $this->assertSame('completed', $b->fresh()->status);
    }

    public function test_cashier_name_appears_in_sales_history_and_details(): void
    {
        $p = Product::factory()->create(['stock' => 100]);
        $mine = $this->sell($p, 1, $this->admin);
        $old = $this->sell($p, 1); // sale from before cashiers were recorded

        $this->get('/sales')->assertOk()->assertSee('Cashier')->assertSee('Kai Cashier');
        $this->get("/sales/{$mine->id}")->assertOk()->assertSee('Kai Cashier');
        $this->get("/sales/{$old->id}")->assertOk()->assertSee('—');
    }

    public function test_receipt_shows_the_sale_and_prints_only_when_asked(): void
    {
        Setting::set('business_name', 'Kai Takoyaki Stand');
        Setting::set('receipt_footer', 'Salamat po! Balik kayo.');
        $p = Product::factory()->create(['name' => 'Classic Takoyaki', 'price' => 50, 'stock' => 100]);
        $sale = $this->sell($p, 3, $this->admin);

        $this->get("/sales/{$sale->id}/receipt")->assertOk()
            ->assertSee('Kai Takoyaki Stand')
            ->assertSee('#'.$sale->id)
            ->assertSee('Classic Takoyaki')
            ->assertSee('150.00')
            ->assertSee('Kai Cashier')
            ->assertSee('Salamat po! Balik kayo.')
            ->assertSee('images/stockrage-logo.png', false)
            ->assertDontSee('VOIDED')
            ->assertDontSee('setTimeout(() => window.print()', false);

        $this->get("/sales/{$sale->id}/receipt?print=1")->assertOk()->assertSee('setTimeout(() => window.print()', false);

        app(SaleService::class)->void($sale);
        $this->get("/sales/{$sale->id}/receipt")->assertOk()->assertSee('VOIDED');
    }

    public function test_receipt_needs_login_and_settings_can_save_the_footer(): void
    {
        $sale = $this->sell(Product::factory()->create(['stock' => 10]), 1);
        auth()->logout();
        $this->get("/sales/{$sale->id}/receipt")->assertRedirect('/login');

        $this->actingAs($this->admin);
        $this->put('/settings', ['business_name' => 'Shop', 'default_low_stock_threshold' => 5, 'receipt_footer' => 'Hello!'])->assertRedirect();
        $this->assertSame('Hello!', Setting::get('receipt_footer'));
        $this->get('/settings')->assertOk()->assertSee('Receipt footer');
    }

    public function test_pos_offers_a_print_receipt_link_and_products_without_photos_get_art(): void
    {
        Product::factory()->create(['name' => 'No Photo Takoyaki', 'stock' => 10, 'image' => null]);

        $this->get('/pos')->assertOk()
            ->assertSee('Print receipt')
            ->assertSee('aria-label="Takoyaki"', false);

        $this->get('/products')->assertOk()->assertSee('aria-label="Takoyaki"', false);
    }

    public function test_sidebar_is_plain_and_human_with_a_hide_menu_button(): void
    {
        Product::factory()->create(['stock' => 3, 'low_stock_threshold' => 10]);
        $this->sell(Product::factory()->create(['price' => 50, 'stock' => 100]), 2, $this->admin);

        $this->get('/dashboard')->assertOk()
            ->assertSee('Hide menu')
            ->assertSee('Show menu')
            ->assertSee('stockrage.sidebar', false)
            ->assertSee('Sales history')
            ->assertSee('Stock history')
            ->assertSee('1 running low')                 // plain-words hint under Products
            ->assertSee('Today so far')
            ->assertSee('100.00')                         // today's total: 2 x 50
            ->assertSee('1 sale')
            ->assertSee('Kai Cashier')
            // logo sits straight on the dark sidebar, no white panel
            ->assertSee('images/stockrage-logo-light.png', false)
            ->assertDontSee('bg-white px-3 py-3', false)
            // the Pinoy theme and the old section headings are gone
            ->assertDontSee('Bentahan')
            ->assertDontSee('Kumusta')
            ->assertDontSee('Mag-log out')
            ->assertDontSee('#fcd116', false)
            ->assertDontSee('Insights');
    }

    public function test_pos_shows_the_four_size_cards_and_every_menu_item_is_reachable(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);
        $this->assertSame(16, Product::count()); // 12 sized + 4 single flavors

        $view = $this->get('/pos')->assertOk()
            ->assertSee('Choose a size')
            ->assertSee('Add to order');
        $sizes = $view->viewData('sizes');

        $this->assertSame(['Solo', 'Barkada', 'Family Tray', 'Party Tray'], $sizes->pluck('label')->all());
        $this->assertSame([false, true, true, true], $sizes->pluck('mixable')->all()); // only the trays mix
        $this->assertSame([5, 12, 25, 50], $sizes->pluck('pcs')->all());

        // Solo: the three flavors, one choice. Trays: the same three, plus the single flavors to mix.
        $this->assertSame(["Bacon N' Cheese", 'Mix', 'Overload'], collect($sizes->first()['flavors'])->pluck('flavor')->all());
        $family = collect($sizes->firstWhere('label', 'Family Tray')['flavors']);
        $this->assertEqualsCanonicalizing(['Classic Takoyaki', 'Cheese Takoyaki', 'Crab Stick Takoyaki', 'Octobits'], $family->pluck('flavor')->slice(3)->values()->all());
        $this->assertSame(['Mix'], $family->where('exclusive', true)->pluck('flavor')->values()->all());

        // The catalog has all 16 with live stock; the 4 unsized ones are also plain cards.
        $this->assertCount(16, $view->viewData('catalog'));
        $this->assertCount(4, $view->viewData('others'));
    }

    public function test_a_product_without_flavor_and_size_is_its_own_card(): void
    {
        Product::factory()->create(['name' => 'Milk Tea', 'stock' => 5]);

        $view = $this->get('/pos')->assertOk();
        $this->assertCount(0, $view->viewData('sizes'));
        $this->assertCount(1, $view->viewData('others'));
    }

    public function test_a_real_photo_in_public_images_menu_replaces_the_drawing(): void
    {
        $this->assertSame('bacon', \App\Support\MenuArt::kind("Bacon N' Cheese - Solo (5 pcs)"));
        $this->assertSame('classic', \App\Support\MenuArt::kind('Milk Tea'));
        $this->assertNull(\App\Support\MenuArt::photoUrl('mix'));
        $this->assertStringContainsString('<svg', view('components.takoyaki-art', ['kind' => 'mix'])->render());

        $file = public_path('images/menu/mix.jpg');
        file_put_contents($file, 'fake');
        try {
            $this->assertStringEndsWith('images/menu/mix.jpg', \App\Support\MenuArt::photoUrl('mix'));
            $html = view('components.takoyaki-art', ['kind' => 'mix'])->render();
            $this->assertStringContainsString('<img', $html);
            $this->assertStringNotContainsString('<svg', $html);
        } finally {
            unlink($file);
        }
    }
}
