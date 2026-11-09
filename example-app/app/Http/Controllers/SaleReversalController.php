<?php

namespace App\Http\Controllers;

use App\Actions\Sales\ReverseSale;
use App\Data\SaleReversalData;
use App\Models\Sale;
use App\Models\Team;
use App\Services\Sales\ReversalAttempts;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaleReversalController extends Controller
{
    public function store(Request $request, string $current_team, string $sale, ReverseSale $action): JsonResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $record = Sale::where('team_id', $store->id)->findOrFail($sale);
        try {
            $result = $action->handle($store, $request->user(), $record, $request->all());

            return response()->json($result['body'], $result['status']);
        } catch (ValidationException $error) {
            return response()->json(['error' => ['code' => 'validation_failed', 'message' => 'Review the refund request.', 'fieldErrors' => $error->errors(), 'issues' => []]], 422);
        }
    }

    public function show(Request $request, string $current_team, string $sale): JsonResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $record = Sale::where('team_id', $store->id)->findOrFail($sale);
        try {
            $reversal = $record->reversal;

            return response()->json($reversal ? ['status' => 'recorded', 'reversal' => SaleReversalData::make($reversal)] : ['status' => 'not_recorded']);
        } catch (QueryException $error) {
            report($error);

            return $this->unknown();
        }
    }

    public function attempt(Request $request, string $current_team, string $sale, string $key): JsonResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $record = Sale::where('team_id', $store->id)->findOrFail($sale);
        try {
            return response()->json((new ReversalAttempts)->lookup($store, $request->user(), $record, $key));
        } catch (QueryException $error) {
            report($error);

            return $this->unknown();
        }
    }

    private function unknown(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'reversal_status_unknown', 'message' => 'Refund recording status is unknown. Check this sale.', 'fieldErrors' => (object) [], 'issues' => []]], 503);
    }
}
