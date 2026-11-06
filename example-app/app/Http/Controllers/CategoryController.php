<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\SaveCategory;
use App\Data\CatalogData;
use App\Data\InventoryData;
use App\Models\Category;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, string $current_team): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();

        return Inertia::render('categories/index', ['business' => InventoryData::business($store), 'categories' => InventoryData::page(Category::where('team_id', $store->id)->orderBy('name')->paginate(25)->through(fn ($c) => CatalogData::category($c)))]);
    }

    public function store(Request $request, string $current_team, SaveCategory $save): RedirectResponse
    {
        return $this->save($request, $current_team, $save);
    }

    public function update(Request $request, string $current_team, string $category, SaveCategory $save): RedirectResponse
    {
        return $this->save($request, $current_team, $save, $category);
    }

    private function save(Request $request, string $slug, SaveCategory $save, ?string $id = null): RedirectResponse
    {
        $store = Team::where('slug', $slug)->firstOrFail();
        $category = $id ? Category::where('team_id', $store->id)->findOrFail($id) : null;
        $save->handle($store, $request->all(), $category);

        return to_route('categories.index', ['current_team' => $slug]);
    }
}
