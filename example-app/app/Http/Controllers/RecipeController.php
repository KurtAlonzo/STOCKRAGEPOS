<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\SaveRecipe;
use App\Models\CatalogItem;
use App\Models\Team;
use App\Support\StoreCatalogTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    public function update(Request $request, string $current_team, string $product, SaveRecipe $save): RedirectResponse
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        StoreCatalogTransaction::run($store, function () use ($store, $product, $request, $save) {
            $item = CatalogItem::where('team_id', $store->id)->findOrFail($product);
            $data = $request->validate(['components' => 'present|array', 'expectedRevision' => 'required|string']);
            if ((string) $item->revision !== $data['expectedRevision']) {
                throw ValidationException::withMessages(['expectedRevision' => 'This item changed. Reload before saving.']);
            }if ($item->kind === 'retail' || $item->stock_behavior === 'direct') {
                throw ValidationException::withMessages(['components' => 'Direct stock cannot have a recipe.']);
            }$save->handle($store, $item, $data['components']);
            $item->update(['revision' => $item->revision + 1]);
        });

        return to_route('products.edit', ['current_team' => $current_team, 'product' => $product]);
    }
}
