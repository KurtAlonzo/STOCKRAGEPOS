<?php

namespace App\Http\Controllers;

use App\Actions\Pos\CompleteCheckout;
use App\Models\Team;
use App\Services\Pos\CheckoutAttempts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request, string $current_team, CompleteCheckout $checkout): JsonResponse
    {
        try {
            $result = $checkout->handle(Team::where('slug', $current_team)->firstOrFail(), $request->user(), $request->all());

            return response()->json($result['body'], $result['status']);
        } catch (ValidationException $error) {
            return response()->json(['error' => ['code' => 'validation_failed', 'message' => 'Review the checkout request.', 'fieldErrors' => $error->errors(), 'issues' => []]], 422);
        }
    }

    public function show(Request $request, string $current_team, string $idempotencyKey): JsonResponse
    {
        return response()->json((new CheckoutAttempts)->lookup(Team::where('slug', $current_team)->firstOrFail(), $request->user(), $idempotencyKey));
    }
}
