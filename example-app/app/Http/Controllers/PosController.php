<?php

namespace App\Http\Controllers;

use App\Actions\Pos\CreateQuote;
use App\Data\CatalogData;
use App\Data\InventoryData;
use App\Data\PosCatalogData;
use App\Data\PosDraftCatalogData;
use App\Models\Category;
use App\Models\Team;
use App\Services\Pos\PaymentRules;
use App\Services\Pos\QuoteUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request, string $current_team): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();

        return Inertia::render('pos/index', [...PosCatalogData::page($store, $this->filters($request)), 'business' => InventoryData::business($store), 'categories' => Category::where('team_id', $store->id)->where('active', true)->orderBy('name')->get()->map(fn ($category) => CatalogData::category($category)), 'enabledPaymentMethods' => (new PaymentRules)->enabledMethods($store)]);
    }

    public function catalog(Request $request, string $current_team): JsonResponse
    {
        return response()->json(PosCatalogData::page(Team::where('slug', $current_team)->firstOrFail(), $this->filters($request)));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate(['query' => 'nullable|string|max:255', 'categoryId' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
    }

    public function draftCatalog(Request $request, string $current_team): JsonResponse
    {
        $input = $request->validate(['lines' => 'present|array', 'lines.*.id' => 'required|string', 'lines.*.productId' => 'required|integer', 'lines.*.addonIds' => 'present|array', 'lines.*.addonIds.*' => 'integer']);

        return response()->json(PosDraftCatalogData::resolve(Team::where('slug', $current_team)->firstOrFail(), $input['lines']));
    }

    public function quote(Request $request, string $current_team, CreateQuote $create): JsonResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        try {
            return response()->json($create->handle($store, $request->user(), $request->only(['lines', 'payment'])));
        } catch (ValidationException $error) {
            $range = in_array('numeric_range_exceeded', array_merge(...array_values($error->errors())), true);

            return response()->json(['error' => ['code' => $range ? 'numeric_range_exceeded' : 'validation_failed', 'message' => $range ? 'The cart exceeds the supported numeric range.' : 'Review the cart selections.', 'fieldErrors' => $error->errors(), 'issues' => []]], 422);
        } catch (QuoteUnavailable $error) {
            report($error);

            return response()->json(['error' => ['code' => 'quote_unavailable', 'message' => 'Unable to store a quote. Retry with your current cart.', 'fieldErrors' => (object) [], 'issues' => []]], 503);
        }
    }
}
