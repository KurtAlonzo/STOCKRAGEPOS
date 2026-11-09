<?php

namespace Tests\Feature\Sales;

use App\Data\SaleReversalData;
use App\Models\CheckoutAttempt;
use App\Models\Payment;
use App\Models\ReversalAttempt;
use App\Models\Sale;
use App\Models\SaleReversal;
use App\Models\StockOperation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReversalPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function records(): array
    {
        $user = User::factory()->create();
        $checkout = CheckoutAttempt::create(['team_id' => $user->current_team_id, 'actor_id' => $user->id, 'idempotency_key' => 'sale', 'request_json' => [], 'request_hash' => hash('sha256', '{}'), 'status' => 'pending']);
        $sale = Sale::create(['checkout_attempt_id' => $checkout->id, 'team_id' => $user->current_team_id, 'actor_id' => $user->id, 'actor_name' => $user->name, 'reference' => 'SR-00000001', 'status' => 'completed', 'occurred_at' => now(), 'currency' => 'PHP', 'subtotal_minor' => 41000, 'total_minor' => 41000, 'lines_json' => []]);
        Payment::create(['sale_id' => $sale->id, 'method' => 'cash', 'input_json' => ['method' => 'cash', 'tenderedMinor' => 50000], 'received_minor' => 41000, 'change_minor' => 9000, 'recorded_at' => now()]);
        $request = ['saleId' => $sale->id, 'idempotencyKey' => 'refund', 'reason' => 'Returned', 'refundConfirmed' => true, 'refundMethod' => 'maya', 'refundReference' => null, 'restock' => []];
        $attempt = ReversalAttempt::create(['team_id' => $sale->team_id, 'actor_id' => $user->id, 'sale_id' => $sale->id, 'idempotency_key' => 'refund', 'request_json' => $request, 'request_hash' => hash('sha256', json_encode($request)), 'status' => 'pending']);
        $reversal = SaleReversal::create(['team_id' => $sale->team_id, 'sale_id' => $sale->id, 'reversal_attempt_id' => $attempt->id, 'request_json' => $request, 'request_hash' => $attempt->request_hash, 'total_minor' => 41000, 'refund_method' => 'maya', 'refund_reference' => null, 'reason' => 'Returned', 'refund_confirmed' => true, 'actor_id' => $user->id, 'actor_name' => $user->name, 'occurred_at' => now()]);

        return [$sale, $attempt, $reversal];
    }

    public function test_serialization_and_legacy_operations_preserve_original_payment(): void
    {
        [$sale, $attempt, $reversal] = $this->records();
        $data = SaleReversalData::make($reversal);
        $this->assertSame(41000, $data['totalMinor']);
        $this->assertSame('maya', $data['refundMethod']);
        $this->assertSame('refund', $data['idempotencyKey']);
        $this->assertSame([], $data['restock']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(['method' => 'cash', 'tenderedMinor' => 50000], $sale->payment->input_json);
        $legacy = StockOperation::create(['team_id' => $sale->team_id, 'kind' => 'adjustment', 'reason' => 'Legacy', 'actor_id' => $sale->actor_id, 'actor_name' => 'Owner']);
        $this->assertNull($legacy->fresh()->sale_reversal_id);
        $this->assertTrue($sale->reversal->is($reversal));
        $this->assertTrue($reversal->attempt->is($attempt));
    }

    public function test_one_reversal_per_sale_is_enforced_by_database(): void
    {
        [$sale, $attempt, $reversal] = $this->records();
        $next = $attempt->replicate();
        $next->idempotency_key = 'different';
        $next->save();
        $attributes = $reversal->getAttributes();
        unset($attributes['id']);
        $attributes['reversal_attempt_id'] = $next->id;
        $this->expectException(QueryException::class);
        DB::table('sale_reversals')->insert($attributes);
    }

    public function test_operation_identity_is_unique_and_immutable(): void
    {
        [, $attempt] = $this->records();
        try {
            $attempt->update(['request_hash' => 'changed']);
            $this->fail('Identity changed');
        } catch (\LogicException) {
        }
        $this->expectException(QueryException::class);
        $attributes = $attempt->getOriginal();
        unset($attributes['id']);
        DB::table('reversal_attempts')->insert($attributes);
    }

    public function test_reversal_cannot_be_changed_or_deleted(): void
    {
        [, , $reversal] = $this->records();
        try {
            $reversal->update(['reason' => 'Edited']);
            $this->fail('Reversal changed');
        } catch (\LogicException) {
        }
        $this->expectException(\LogicException::class);
        $reversal->delete();
    }

    public function test_historical_links_restrict_deletion(): void
    {
        [, $attempt] = $this->records();
        $this->expectException(QueryException::class);
        DB::table('reversal_attempts')->where('id', $attempt->id)->delete();
    }

    public function test_new_migrations_roll_back_and_reapply_without_changing_existing_sales(): void
    {
        [$sale] = $this->records();
        $links = require database_path('migrations/2026_11_07_000002_add_sale_reversal_id_to_stock_operations_table.php');
        $records = require database_path('migrations/2026_11_07_000001_create_reversal_attempts_and_sale_reversals_tables.php');
        $links->down();
        $records->down();
        $records->up();
        $links->up();
        $this->assertSame(41000, $sale->fresh()->total_minor);
        $this->assertSame(50000, $sale->payment->input_json['tenderedMinor']);
        $this->assertDatabaseCount('sale_reversals', 0);
    }
}
