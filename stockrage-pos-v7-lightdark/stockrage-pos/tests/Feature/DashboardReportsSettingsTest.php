<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function sell(Product $p, int $qty, $at = null): Sale
    {
        return app(SaleService::class)->checkout([['product_id' => $p->id, 'qty' => $qty]], 100000, 'cash', $at);
    }

    public function test_dashboard_numbers_match_sales_history_and_ignore_voided(): void
    {
        $p = Product::factory()->create(['price' => 50, 'stock' => 5000, 'low_stock_threshold' => 20]);
        $low = Product::factory()->create(['name' => 'Almost Gone', 'stock' => 4, 'low_stock_threshold' => 10]);
        Product::factory()->create(['name' => 'Inactive Low', 'stock' => 0, 'is_active' => false]); // inactive: not counted

        $this->sell($p, 2);                       // 100 today
        $this->sell($p, 4);                       // 200 today
        $voided = $this->sell($p, 10);            // 500, voided below
        app(SaleService::class)->void($voided);
        $this->sell($p, 1, now()->subMonthNoOverflow()->startOfMonth()->addDays(2)); // last month: 50

        $res = $this->get('/dashboard')->assertOk();
        $this->assertEquals(300, $res->viewData('todaySales'));
        $this->assertEquals(300, $res->viewData('monthlyRevenue'));
        $this->assertSame(2, $res->viewData('totalOrders'));
        $this->assertSame(['Almost Gone'], $res->viewData('lowStock')->pluck('name')->all());
        // Recent transactions = the 3 completed sales (2 this month + 1 last month); the voided one never appears.
        $this->assertCount(3, $res->viewData('recent'));
        $this->assertCount(0, $res->viewData('recent')->where('status', 'voided'));
        $this->assertCount(6, $res->viewData('monthly'));
        $this->assertCount(14, $res->viewData('daily'));
        $this->assertEquals(50, $res->viewData('monthly')[4]['total']);   // previous month
        $this->assertEquals(300, $res->viewData('monthly')[5]['total']);  // this month
        $this->assertSame(7, $res->viewData('best')->first()['total'] - 0 === 7 ? 7 : $res->viewData('best')->first()['total']);
        $res->assertSee('300.00')->assertSee('Almost Gone');
    }

    public function test_monthly_report_total_equals_dashboard_monthly_revenue(): void
    {
        $p = Product::factory()->create(['price' => 60, 'stock' => 5000]);
        foreach ([0, 1, 2, 5] as $d) { $this->sell($p, 3, now()->startOfMonth()->addDays($d)->setTime(12, 0)); }
        $this->sell($p, 2, now()->subMonthsNoOverflow(2));

        $dash = $this->get('/dashboard')->viewData('monthlyRevenue');
        $report = $this->get('/reports?period=monthly');

        $thisMonth = collect($report->viewData('rows'))->firstWhere('key', now()->format('Y-m'));
        $this->assertEquals($dash, $thisMonth['total']);
        $this->assertSame(4, $thisMonth['orders']);
        $this->assertEquals(4 * 180 + 120, $report->viewData('totalSales'));
    }

    public function test_daily_and_yearly_reports_group_correctly(): void
    {
        $p = Product::factory()->create(['price' => 50, 'stock' => 5000]);
        $this->sell($p, 1, now()->subDays(2)->setTime(9, 0));
        $this->sell($p, 1, now()->subDays(2)->setTime(18, 0));
        $this->sell($p, 4, now()->subDay());

        $daily = $this->get('/reports?period=daily')->viewData('rows');
        $this->assertCount(2, $daily);
        $this->assertSame([2, 1], $daily->pluck('orders')->all());
        $this->assertEquals([100.0, 200.0], $daily->pluck('total')->all());

        $yearly = $this->get('/reports?period=yearly')->viewData('rows');
        $this->assertCount(1, $yearly);
        $this->assertSame(now()->format('Y'), $yearly[0]['label']);
        $this->assertEquals(300, $yearly[0]['total']);

        // custom range that excludes everything
        $empty = $this->get('/reports?period=daily&from='.now()->subYear()->toDateString().'&to='.now()->subYear()->addDay()->toDateString());
        $this->assertCount(0, $empty->viewData('rows'));
        $empty->assertSee('No completed sales in this range.');

        $this->get('/reports?period=weekly')->assertSessionHasErrors('period');
        $this->get('/reports?from=2026-05-02&to=2026-05-01')->assertSessionHasErrors('to');
    }

    public function test_csv_export(): void
    {
        $p = Product::factory()->create(['price' => 50, 'stock' => 5000]);
        $this->sell($p, 2, now()->subDay());
        $this->sell($p, 1);

        $res = $this->get('/reports/export?period=daily')->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $res->headers->get('Content-Disposition'));

        $csv = $res->streamedContent();
        $this->assertStringContainsString('"Daily period",Orders,"Total sales"', $csv);
        $this->assertStringContainsString('TOTAL,2,150.00', $csv);
    }

    public function test_settings_can_be_saved_and_prefill_new_products(): void
    {
        $this->get('/settings')->assertOk()->assertSee('Takoyaki POS');

        $this->put('/settings', ['business_name' => 'Kai Takoyaki Stand', 'default_low_stock_threshold' => 12])->assertSessionHas('success');

        $this->assertSame('Kai Takoyaki Stand', Setting::get('business_name'));
        $this->get('/dashboard')->assertSee('Kai Takoyaki Stand');
        $this->get('/products/create')->assertSee('value="12"', false);

        $this->put('/settings', ['business_name' => '', 'default_low_stock_threshold' => -1])->assertSessionHasErrors(['business_name', 'default_low_stock_threshold']);
    }
}
