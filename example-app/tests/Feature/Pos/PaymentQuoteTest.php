<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Pos\QuoteReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_quotes_validate_tender_and_external_confirmation(): void
    {
        $u = User::factory()->create();
        $item = (new SaveCatalogItem)->handle($u->currentTeam, $u, ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 41000, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
        InventoryItem::whereKey($item->stock_item_id)->update(['balance_thousandths' => 10000]);
        $input = ['lines' => [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $item->id, 'quantity' => 1, 'addonIds' => []]], 'payment' => null];
        $url = '/'.$u->currentTeam->slug.'/pos/quotes';
        $this->actingAs($u)->withHeader('X-POS-Actor-Id', (string) $u->id);
        foreach ([50000 => 9000, 41000 => 0] as $tender => $change) {
            $this->postJson($url, [...$input, 'payment' => ['method' => 'cash', 'tenderedMinor' => $tender]])->assertOk()->assertJsonPath('changeMinor', $change)->assertHeader('X-POS-Actor-Id', (string) $u->id);
        }
        foreach (['gcash', 'maya'] as $method) {
            $this->postJson($url, [...$input, 'payment' => ['method' => $method, 'receivedConfirmed' => true, 'reference' => '  ']])->assertOk()->assertJsonPath('payment.reference', null);
            $this->postJson($url, [...$input, 'payment' => ['method' => $method, 'receivedConfirmed' => false, 'reference' => null]])->assertUnprocessable();
        }
        $this->postJson($url, [...$input, 'payment' => ['method' => 'gcash', 'receivedConfirmed' => true, 'reference' => '0']])->assertOk()->assertJsonPath('payment.reference', '0');
        $this->postJson($url, [...$input, 'payment' => ['method' => 'cash', 'tenderedMinor' => 40000]])->assertUnprocessable();
        config(['pos.payment_methods' => ['cash']]);
        $this->postJson($url, [...$input, 'payment' => ['method' => 'maya', 'receivedConfirmed' => true, 'reference' => null]])->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_semantic_review_ignores_fresh_identity_and_positive_stock(): void
    {
        $quote = ['id' => 'old', 'revision' => 'one', 'expiresAt' => 'old', 'stockEffects' => [['before' => '5.000', 'after' => '4.000']], 'lines' => [['name' => 'Water']], 'warnings' => [], 'blockingIssues' => [], 'payment' => null, 'totalMinor' => 5000];
        $next = [...$quote, 'id' => 'new', 'revision' => 'two', 'expiresAt' => 'new', 'stockEffects' => [['before' => '6.000', 'after' => '5.000']]];
        $this->assertFalse(QuoteReview::changed($quote, $next));
        $this->assertTrue(QuoteReview::changed($quote, [...$next, 'totalMinor' => 6000]));
        $this->assertTrue(QuoteReview::changed($quote, [...$next, 'warnings' => [['id' => 'new']]]));
    }
}
