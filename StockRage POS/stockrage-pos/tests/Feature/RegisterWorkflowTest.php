<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_mix_recipes_deduct_and_restore_exact_pieces(): void
    {
        $this->seed();
        $sales = app(SaleService::class);
        foreach (['Solo' => [3, 3], 'Barkada' => [6, 6], 'Family Tray' => [13, 12], 'Party Tray' => [25, 25]] as $size => [$bacon, $over]) {
            $product = Product::where('name', 'like', "Mix - $size (%")->firstOrFail();
            $sale = $sales->checkout([], 2000, 'cash', bundles: [[
                'size' => $size, 'qty' => 2, 'parts' => [['product_id' => $product->id]],
            ]]);
            $this->assertPools(100 - 2 * $bacon, 100 - 2 * $over);
            $sales->void($sale);
            $this->assertPools(100, 100);
        }
    }

    public function test_restored_flavors_can_be_sold_and_size_recipes_do_not_trigger_stock_alerts(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', config('pos.admin_email'))->firstOrFail());

        $this->get('/dashboard')->assertOk()->assertSee('Nothing is running low')->assertDontSee('12 running low');
        $this->get('/pos')->assertOk()->assertSee('Classic Takoyaki')->assertSee('Cheese Takoyaki')
            ->assertSee('Crab Stick Takoyaki')->assertSee('Octobits');

        $cheese = Product::where('name', 'Cheese Takoyaki')->firstOrFail();
        $sales = app(SaleService::class);
        $sale = $sales->checkout([], 500, 'cash', bundles: [[
            'size' => 'Party Tray', 'parts' => [['product_id' => $cheese->id, 'pieces' => 1]],
        ]]);
        $this->assertSame(50, $cheese->fresh()->stock);
        $this->assertPools(100, 100);
        $sales->void($sale);
        $this->assertSame(100, $cheese->fresh()->stock);
    }

    public function test_sizes_share_pieces_and_overdraw_rolls_back_checkout(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', config('pos.admin_email'))->firstOrFail());
        $bacon = Product::where('name', "Bacon N' Cheese - Party Tray (50 pcs)")->firstOrFail();
        $over = Product::where('name', 'Overload - Party Tray (50 pcs)')->firstOrFail();
        $order = ['size' => 'Party Tray', 'qty' => 1, 'parts' => [['product_id' => $bacon->id, 'pieces' => 50]]];
        $pay = fn ($bundle) => $this->postJson('/pos/checkout', ['bundles' => [$bundle], 'amount_paid' => 2000, 'payment_method' => 'cash']);
        $pay($order)->assertOk()->assertJsonPath('pos_url', route('pos.index'));
        $this->get('/pos')->assertOk()->assertSee('View last receipt')->assertSee('Change due');
        $this->assertPools(50, 100);
        $order['parts'] = [['product_id' => $bacon->id, 'pieces' => 30], ['product_id' => $over->id, 'pieces' => 20]];
        $pay($order)->assertOk();
        $this->assertPools(20, 80);
        $pay($order)->assertStatus(422);
        $this->assertPools(20, 80);
        $this->assertDatabaseCount('sales', 2);
    }

    public function test_print_contains_all_matching_sales_in_chronological_order(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', config('pos.admin_email'))->firstOrFail());
        $this->travelTo(now()->setDate(2026, 11, 20));
        foreach (range(0, 17) as $i) {
            $sale = new Sale(['total' => 0, 'amount_paid' => 0, 'change_amount' => 0, 'payment_method' => 'cash', 'status' => 'completed']);
            $sale->created_at = today()->startOfMonth()->addDays($i - 1);
            $sale->save();
        }
        $this->get('/sales')->assertViewHas('sales', fn ($sales) => $sales->pluck('id')->all() === range(18, 4));
        $this->get('/sales?print=1')->assertViewHas('sales', fn ($sales) => $sales->pluck('id')->all() === range(2, 18));
    }

    private function assertPools(int $bacon, int $over): void
    {
        $this->assertDatabaseHas('products', ['stock_flavor' => "Bacon N' Cheese", 'stock' => $bacon]);
        $this->assertDatabaseHas('products', ['stock_flavor' => 'Overload', 'stock' => $over]);
    }
}
