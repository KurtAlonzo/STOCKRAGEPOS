<?php

namespace Tests\Feature\Pos;

use App\Data\SaleData;
use App\Models\CheckoutAttempt;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function sale(): Sale
    {
        $user = User::factory()->create();
        $attempt = CheckoutAttempt::create(['team_id' => $user->current_team_id, 'actor_id' => $user->id, 'idempotency_key' => 'original', 'request_json' => [], 'request_hash' => hash('sha256', '{}'), 'status' => 'pending']);

        return Sale::create(['checkout_attempt_id' => $attempt->id, 'team_id' => $user->current_team_id, 'actor_id' => $user->id, 'actor_name' => $user->name, 'reference' => 'SR-00000001', 'status' => 'completed', 'occurred_at' => now(), 'currency' => 'PHP', 'subtotal_minor' => 41000, 'total_minor' => 41000, 'lines_json' => [['name' => 'Original burger']]]);
    }

    public function test_sale_snapshots_survive_catalog_changes(): void
    {
        $sale = $this->sale();
        Payment::create(['sale_id' => $sale->id, 'method' => 'cash', 'input_json' => ['method' => 'cash', 'tenderedMinor' => 50000], 'received_minor' => 41000, 'change_minor' => 9000, 'recorded_at' => now()]);
        $result = SaleData::make($sale->fresh());
        $this->assertSame(41000, $result['payment']['receivedMinor']);
        $this->assertSame(9000, $result['payment']['changeMinor']);
        $this->assertSame('Original burger', $result['lines'][0]['name']);
        $this->assertNull($result['reversal']);
        $this->expectException(\LogicException::class);
        $sale->update(['total_minor' => 1]);
    }

    public function test_attempt_identity_and_one_payment_per_sale_are_unique(): void
    {
        $sale = $this->sale();
        $data = ['sale_id' => $sale->id, 'method' => 'cash', 'input_json' => ['method' => 'cash', 'tenderedMinor' => 50000], 'received_minor' => 41000, 'change_minor' => 9000, 'recorded_at' => now()];
        Payment::create($data);
        $this->expectException(QueryException::class);
        Payment::create($data);
    }

    public function test_financial_snapshots_cannot_be_deleted(): void
    {
        $sale = $this->sale();
        $this->expectException(\LogicException::class);
        $sale->delete();
    }
}
