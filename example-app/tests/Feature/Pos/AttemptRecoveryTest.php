<?php

namespace Tests\Feature\Pos;

use App\Models\User;
use App\Services\Pos\CheckoutAttempts;
use App\Services\Pos\CheckoutRequest;
use App\Services\Pos\IdempotencyConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttemptRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_is_scoped_and_canonical_but_changed_requests_conflict(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $request = ['idempotencyKey' => '11111111-1111-4111-8111-111111111111', 'quoteId' => '22222222-2222-4222-8222-222222222222', 'quoteRevision' => '1', 'lines' => [['id' => '33333333-3333-4333-8333-333333333333', 'productId' => 1, 'quantity' => 1, 'addonIds' => [3, 2]]], 'payment' => ['method' => 'gcash', 'receivedConfirmed' => true, 'reference' => ' 0 '], 'acknowledgedWarningIds' => ['z', 'a']];
        $service = new CheckoutAttempts;
        $attempt = $service->register($a->currentTeam, $a, $request);
        $reordered = $request;
        $reordered['lines'][0]['addonIds'] = [2, 3];
        $reordered['acknowledgedWarningIds'] = ['a', 'z'];
        $reordered['payment']['reference'] = '0';
        $this->assertSame($attempt->id, $service->register($a->currentTeam, $a, $reordered)->id);
        $this->assertSame('0', CheckoutRequest::canonical($request)['payment']['reference']);
        $this->assertSame(['status' => 'not_found'], $service->lookup($a->currentTeam, $b, $request['idempotencyKey']));
        $this->assertSame(['status' => 'not_found'], $service->lookup($b->currentTeam, $a, $request['idempotencyKey']));
        $service->register($b->currentTeam, $a, $request);
        $service->register($a->currentTeam, $b, $request);
        $this->assertDatabaseCount('checkout_attempts', 3);
        $this->expectException(IdempotencyConflict::class);
        $service->register($a->currentTeam, $a, [...$request, 'quoteRevision' => 'changed']);
    }

    public function test_duplicate_line_or_addon_ids_cannot_register_an_attempt(): void
    {
        $this->expectException(ValidationException::class);
        CheckoutRequest::canonical(['idempotencyKey' => '11111111-1111-4111-8111-111111111111', 'quoteId' => '22222222-2222-4222-8222-222222222222', 'quoteRevision' => '1', 'lines' => [['id' => '33333333-3333-4333-8333-333333333333', 'productId' => 1, 'quantity' => 1, 'addonIds' => [2, 2]]], 'payment' => ['method' => 'cash', 'tenderedMinor' => 1], 'acknowledgedWarningIds' => []]);
    }
}
