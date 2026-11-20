<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\BundleService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bundles: one tray size split between flavors by pieces. */
class BundleTest extends TestCase
{
    use RefreshDatabase;

    private Product $bacon;
    private Product $mix;
    private Product $over;
    private Product $soloBacon;
    private Product $soloMix;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.discounts' => []]); // these tests are about pieces and stock; the discount has its own tests below
        $this->actingAs(User::factory()->create());

        $make = fn (string $name, float $price, float $cost) => Product::factory()->create(['name' => $name, 'price' => $price, 'cost_price' => $cost, 'stock' => 10, 'low_stock_threshold' => 2]);
        $this->bacon = $make("Bacon N' Cheese - Family Tray (25 pcs)", 240, 100);
        $this->mix = $make('Mix - Family Tray (25 pcs)', 325, 150);
        $this->over = $make('Overload - Family Tray (25 pcs)', 290, 125);
        // Solo has a different number of pieces per flavor, so it cannot be a bundle.
        $this->soloBacon = $make("Bacon N' Cheese - Solo (5 pcs)", 55, 20);
        $this->soloMix = $make('Mix - Solo (6 pcs)', 75, 30);
        Product::factory()->create(['name' => 'Classic Takoyaki', 'stock' => 10]);
    }

    private function bundle(array $parts, string $size = 'Family Tray', int $qty = 1): array
    {
        return ['size' => $size, 'qty' => $qty, 'parts' => array_map(fn ($p) => ['product_id' => $p[0]->id, 'pieces' => $p[1]], $parts)];
    }

    public function test_the_mix_bundle_uses_mix_sizes_and_never_offers_mix_as_a_flavor(): void
    {
        $sizes = app(BundleService::class)->sizes();

        // Solo is never mixed, so it is not a mixable size even with two flavors.
        $this->assertSame(['Family Tray'], $sizes->pluck('label')->all());
        $this->assertSame(25, $sizes->first()['pcs']);
        $this->assertSame(["Bacon N' Cheese", 'Overload'], collect($sizes->first()['flavors'])->pluck('flavor')->all());
        $this->assertNotContains($this->mix->id, collect($sizes->first()['flavors'])->pluck('product_id')->all());

        $this->get('/pos')->assertOk()->assertSee('Choose a size');
    }

    public function test_all_sizes_are_listed_with_solo_not_mixable_and_mix_ready_made(): void
    {
        $trays = app(BundleService::class)->trays();

        $this->assertSame(['Solo', 'Family Tray'], $trays->pluck('label')->all());
        $this->assertSame([false, true], $trays->pluck('mixable')->all());
        $this->assertSame([$this->mix->id], collect($trays->last()['flavors'])->where('exclusive', true)->pluck('product_id')->all());
    }

    public function test_a_mixed_tray_can_be_sold_several_times_at_once(): void
    {
        // 2 trays of 13 Bacon (124.80 -> 125) + 12 Overload (139.20 -> 139) = 264 each, 528 in all
        $this->postJson('/pos/checkout', [
            'bundles' => [$this->bundle([[$this->bacon, 13], [$this->over, 12]], 'Family Tray', 2)],
            'amount_paid' => 600,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 528, 'change' => 72]);

        $line = Sale::firstOrFail()->items->firstWhere('product_id', $this->bacon->id);
        $this->assertSame(2, $line->qty);
        $this->assertEquals(125, $line->price); // 124.80 rounded to whole pesos
        $this->assertSame(8, $this->bacon->fresh()->stock);
        $this->assertSame(8, $this->over->fresh()->stock);
    }

    public function test_a_tray_needs_a_flavor_and_a_sensible_quantity(): void
    {
        foreach ([
            $this->bundle([]),                                                     // no flavor at all
            $this->bundle([[$this->bacon, 13], [$this->over, 12]], 'Family Tray', 0),
            $this->bundle([[$this->bacon, 13], [$this->over, 12]], 'Family Tray', 101),
        ] as $bundle) {
            $this->postJson('/pos/checkout', ['bundles' => [$bundle], 'amount_paid' => 9999, 'payment_method' => 'cash'])->assertStatus(422);
        }
        $this->assertSame(0, Sale::count());
    }

    public function test_a_bundle_is_priced_by_the_pieces_and_takes_one_set_per_flavor(): void
    {
        // 10 Bacon (240/25 = 9.60 each) + 15 Overload (290/25 = 11.60 each) = 96.00 + 174.00 = 270.00
        $this->postJson('/pos/checkout', [
            'bundles' => [$this->bundle([[$this->bacon, 10], [$this->over, 15]])],
            'amount_paid' => 300,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['ok' => true, 'total' => 270, 'change' => 30]);

        $sale = Sale::with('items')->firstOrFail();
        $this->assertCount(2, $sale->items);
        $this->assertEquals(270, $sale->items->sum('subtotal'));

        $line = $sale->items->firstWhere('product_id', $this->bacon->id);
        $this->assertEquals(96, $line->price);
        $this->assertEquals(40, $line->cost); // 10/25 of the 100 cost
        $this->assertSame('Mix bundle · Family Tray (25 pcs): 10 pcs', $line->note);
        $this->assertSame(1, $line->qty);

        foreach ([$this->bacon, $this->over] as $p) {
            $this->assertSame(9, $p->fresh()->stock);
        }
        $this->assertSame(10, $this->mix->fresh()->stock); // Mix itself is not used up
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->over->id, 'type' => 'sale', 'qty_change' => -1]);

        // Detail page and receipt show what was in the bundle.
        $this->get("/sales/{$sale->id}")->assertOk()->assertSee('Mix bundle · Family Tray (25 pcs): 10 pcs');
        $this->get("/sales/{$sale->id}/receipt")->assertOk()->assertSee('Mix bundle · Family Tray (25 pcs): 15 pcs');
    }

    public function test_bundles_and_normal_items_can_be_sold_together_and_voided(): void
    {
        $this->postJson('/pos/checkout', [
            'items' => [['product_id' => $this->bacon->id, 'qty' => 2]], // 2 whole Bacon trays: 480
            'bundles' => [$this->bundle([[$this->bacon, 20], [$this->over, 5]])], // 192 + 58 = 250
            'amount_paid' => 730,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 730]);

        $this->assertSame(7, $this->bacon->fresh()->stock); // 2 + 1 sets

        $sale = Sale::firstOrFail();
        app(SaleService::class)->void($sale);
        $this->assertSame(10, $this->bacon->fresh()->stock);
        $this->assertSame(10, $this->over->fresh()->stock);
    }

    public function test_a_bundle_that_does_not_add_up_is_refused_and_nothing_is_saved(): void
    {
        $bad = [
            $this->bundle([[$this->bacon, 10], [$this->over, 10]]),                        // 20, not 25
            $this->bundle([[$this->bacon, 20], [$this->over, 10]]),                        // 30, too many
            $this->bundle([[$this->mix, 10], [$this->bacon, 15]]),                         // Mix is not a flavor choice
            $this->bundle([[$this->bacon, 3], [$this->soloBacon, 2]], 'Solo'),             // Solo is never mixed
            $this->bundle([[$this->soloBacon, 25]]),                                       // wrong size's product
            $this->bundle([[$this->bacon, 10], [$this->bacon, 15]]),                       // same flavor twice
            $this->bundle([[$this->bacon, 0], [$this->over, 25]]),                          // zero pieces
            $this->bundle([[$this->bacon, 4], [$this->over, 21]]),                          // under the 5 pcs minimum
        ];

        foreach ($bad as $bundle) {
            $this->postJson('/pos/checkout', ['bundles' => [$bundle], 'amount_paid' => 1000, 'payment_method' => 'cash'])
                ->assertStatus(422);
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(10, $this->bacon->fresh()->stock);
    }

    public function test_bundle_respects_stock_and_payment(): void
    {
        $this->over->update(['stock' => 0]);
        $this->postJson('/pos/checkout', ['bundles' => [$this->bundle([[$this->bacon, 10], [$this->over, 15]])], 'amount_paid' => 1000, 'payment_method' => 'cash'])
            ->assertStatus(422);
        $this->assertSame(10, $this->bacon->fresh()->stock); // rolled back

        $this->postJson('/pos/checkout', ['bundles' => [$this->bundle([[$this->bacon, 20], [$this->over, 5]])], 'amount_paid' => 100, 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJson(['message' => 'Payment is not enough']);
        $this->assertSame(10, $this->bacon->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_checkout_needs_items_or_bundles_and_login(): void
    {
        $this->postJson('/pos/checkout', ['amount_paid' => 100, 'payment_method' => 'cash'])->assertStatus(422);

        auth()->logout();
        $this->postJson('/pos/checkout', ['bundles' => [$this->bundle([[$this->bacon, 20], [$this->over, 5]])], 'amount_paid' => 500, 'payment_method' => 'cash'])->assertStatus(401);
    }

    public function test_single_size_flavors_with_pieces_per_order_can_join_a_bundle(): void
    {
        $classic = Product::where('name', 'Classic Takoyaki')->firstOrFail();
        $classic->update(['price' => 60, 'cost_price' => 24, 'pieces' => 6]); // 10.00 per piece

        $sizes = app(BundleService::class)->sizes();
        $this->assertContains($classic->id, collect($sizes->first()['flavors'])->pluck('product_id')->all());
        $this->assertSame(6, collect($sizes->first()['flavors'])->firstWhere('product_id', $classic->id)['unit_pcs']);

        // 5 Classic (50.00) + 20 Bacon (9.60 each = 192.00) = 242.00
        $this->postJson('/pos/checkout', [
            'bundles' => [$this->bundle([[$classic, 5], [$this->bacon, 20]])],
            'amount_paid' => 300,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 242]);

        $line = Sale::firstOrFail()->items->firstWhere('product_id', $classic->id);
        $this->assertEquals(50, $line->price);
        $this->assertEquals(20, $line->cost);
        $this->assertSame(9, $classic->fresh()->stock);
    }

    public function test_a_single_flavor_without_pieces_is_not_offered_or_accepted(): void
    {
        $classic = Product::where('name', 'Classic Takoyaki')->firstOrFail();
        $this->assertNotContains($classic->id, collect(app(BundleService::class)->sizes()->first()['flavors'])->pluck('product_id')->all());

        $this->postJson('/pos/checkout', [
            'bundles' => [$this->bundle([[$classic, 5], [$this->bacon, 20]])],
            'amount_paid' => 300,
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_pieces_per_order_can_be_saved_on_a_product(): void
    {
        $this->post('/products', ['name' => 'Octobits', 'price' => 70, 'pieces' => 8, 'low_stock_threshold' => 5, 'is_active' => 1])->assertRedirect('/products');
        $this->assertSame(8, Product::where('name', 'Octobits')->firstOrFail()->pieces);

        $this->post('/products', ['name' => 'Bad', 'price' => 70, 'pieces' => 0, 'low_stock_threshold' => 5])->assertSessionHasErrors('pieces');
    }

    public function test_seeded_custom_mix_choices_use_only_the_two_stocked_flavors(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);
        $flavors = app(BundleService::class)->sizes()->firstWhere('label', 'Family Tray')['flavors'];
        $this->assertSame(["Bacon N' Cheese", 'Overload'], array_column($flavors, 'flavor'));
    }

    public function test_one_flavor_in_a_tray_is_charged_the_tray_price_not_the_flavor_price(): void
    {
        $classic = Product::where('name', 'Classic Takoyaki')->firstOrFail();
        $classic->update(['price' => 50, 'cost_price' => 20, 'pieces' => 6, 'stock' => 10]);

        // Bacon Family Tray: its own tray price (240). Classic Family Tray: 50 per 6 pcs spread over 25 pcs = 208.
        $this->postJson('/pos/checkout', [
            'bundles' => [
                ['size' => 'Family Tray', 'qty' => 2, 'parts' => [['product_id' => $this->bacon->id, 'pieces' => 25]]],
                ['size' => 'Family Tray', 'qty' => 1, 'parts' => [['product_id' => $classic->id, 'pieces' => 25]]],
            ],
            'amount_paid' => 1000,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 688]);

        $sale = Sale::with('items')->firstOrFail();
        $this->assertEquals(240, $sale->items->firstWhere('product_id', $this->bacon->id)->price);
        $this->assertSame(2, $sale->items->firstWhere('product_id', $this->bacon->id)->qty);
        $this->assertEquals(208, $sale->items->firstWhere('product_id', $classic->id)->price);
        $this->assertSame('Family Tray (25 pcs)', $sale->items->firstWhere('product_id', $this->bacon->id)->note);
        $this->assertSame(8, $this->bacon->fresh()->stock);
        $this->assertSame(9, $classic->fresh()->stock);
    }

    public function test_solo_takes_exactly_one_flavor_from_products(): void
    {
        $solo = app(BundleService::class)->trays()->firstWhere('label', 'Solo');
        $this->assertFalse($solo['mixable']);

        $this->postJson('/pos/checkout', [
            'bundles' => [['size' => 'Solo', 'qty' => 3, 'parts' => [['product_id' => $this->soloBacon->id, 'pieces' => 5]]]],
            'amount_paid' => 200,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 165, 'change' => 35]);
        $this->assertSame(7, $this->soloBacon->fresh()->stock);

        // two flavors in a Solo are refused
        $this->postJson('/pos/checkout', [
            'bundles' => [['size' => 'Solo', 'parts' => [['product_id' => $this->soloBacon->id, 'pieces' => 3], ['product_id' => $this->soloMix->id, 'pieces' => 2]]]],
            'amount_paid' => 500,
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_the_ready_made_mix_can_be_sold_alone_but_not_combined(): void
    {
        $this->postJson('/pos/checkout', [
            'bundles' => [['size' => 'Family Tray', 'parts' => [['product_id' => $this->mix->id, 'pieces' => 25]]]],
            'amount_paid' => 400,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 325]);
    }

    public function test_tray_flavors_carry_the_price_of_a_whole_tray(): void
    {
        $classic = Product::where('name', 'Classic Takoyaki')->firstOrFail();
        $classic->update(['price' => 50, 'pieces' => 6]);

        $family = app(BundleService::class)->trays()->firstWhere('label', 'Family Tray');
        $prices = collect($family['flavors'])->pluck('price', 'flavor');

        $this->assertEquals(240, $prices["Bacon N' Cheese"]);
        $this->assertEquals(325, $prices['Mix']);
        $this->assertEquals(208, $prices['Classic Takoyaki']);

        // the POS page shows only the size cards: no loose "Other flavors"
        $this->get('/pos')->assertOk()->assertDontSee('Other flavors');
    }

    public function test_a_mix_gives_each_flavor_at_least_five_pieces(): void
    {
        $family = app(BundleService::class)->trays()->firstWhere('label', 'Family Tray');
        $this->assertSame(5, $family['max_flavors']);

        // exactly 5 pcs is fine: 5 Bacon + 20 Overload = 48 + 232
        $this->postJson('/pos/checkout', [
            'bundles' => [$this->bundle([[$this->bacon, 5], [$this->over, 20]])],
            'amount_paid' => 300,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 280]);
    }

    public function test_bigger_sizes_get_a_discount_and_solo_does_not(): void
    {
        config(['pos.discounts' => ['family-tray' => 10]]);

        $trays = app(BundleService::class)->trays();
        $family = $trays->firstWhere('label', 'Family Tray');
        $this->assertSame(10, $family['discount']);
        $this->assertEquals(216, collect($family['flavors'])->firstWhere('product_id', $this->bacon->id)['price']); // 240 - 10%
        $this->assertEquals(240, collect($family['flavors'])->firstWhere('product_id', $this->bacon->id)['list_price']);
        $this->assertSame(0, $trays->firstWhere('label', 'Solo')['discount']);
        $this->assertEquals(55, collect($trays->firstWhere('label', 'Solo')['flavors'])->firstWhere('product_id', $this->soloBacon->id)['price']);

        // one flavor: 216. A mix of 13 Bacon + 12 Overload: 112 + 125 = 237 (before: 264)
        $this->postJson('/pos/checkout', [
            'bundles' => [
                ['size' => 'Family Tray', 'parts' => [['product_id' => $this->bacon->id, 'pieces' => 25]]],
                $this->bundle([[$this->bacon, 13], [$this->over, 12]]),
            ],
            'amount_paid' => 500,
            'payment_method' => 'cash',
        ])->assertOk()->assertJson(['total' => 453]);
    }

    public function test_the_cashier_chooses_the_pieces_and_each_size_has_its_own_minimum(): void
    {
        $make = fn (string $name, float $price) => Product::factory()->create(['name' => $name, 'price' => $price, 'cost_price' => 50, 'stock' => 10, 'low_stock_threshold' => 2]);
        $b1 = $make("Bacon N' Cheese - Barkada (12 pcs)", 120);
        $b2 = $make('Overload - Barkada (12 pcs)', 145);
        config(['pos.discounts' => ['barkada' => 5]]);

        $barkada = app(BundleService::class)->trays()->firstWhere('label', 'Barkada');
        $this->assertSame(2, $barkada['min_pieces']);
        $this->assertSame(6, $barkada['max_flavors']);
        $this->assertSame(5, app(BundleService::class)->trays()->firstWhere('label', 'Family Tray')['min_pieces']);

        // 1 pc is under the Barkada minimum of 2
        $this->postJson('/pos/checkout', ['bundles' => [['size' => 'Barkada', 'parts' => [['product_id' => $b1->id, 'pieces' => 1], ['product_id' => $b2->id, 'pieces' => 11]]]], 'amount_paid' => 500, 'payment_method' => 'cash'])->assertStatus(422);

        // 2 + 10: 19 + 115 = 134 (95% of the pieces' share of 120 and 145)
        $this->postJson('/pos/checkout', ['bundles' => [['size' => 'Barkada', 'parts' => [['product_id' => $b1->id, 'pieces' => 2], ['product_id' => $b2->id, 'pieces' => 10]]]], 'amount_paid' => 200, 'payment_method' => 'cash'])
            ->assertOk()->assertJson(['total' => 134, 'change' => 66]);
    }
}
