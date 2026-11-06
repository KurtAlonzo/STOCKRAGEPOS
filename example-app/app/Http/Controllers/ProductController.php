<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\SaveCatalogItem;
use App\Data\CatalogData;
use App\Data\InventoryData;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\RecipeComponent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, string $current_team): Response
    {
        $store = Team::where('slug', $current_team)->firstOrFail();
        $f = $request->validate(['query' => 'nullable|string|max:255', 'kind' => 'nullable|in:retail,food,addon', 'categoryId' => 'nullable|integer', 'active' => 'nullable|in:0,1', 'page' => 'nullable|integer|min:1']);
        if (isset($f['categoryId'])) {
            Category::where('team_id', $store->id)->findOrFail($f['categoryId']);
        }
        $q = CatalogItem::with(['stockItem', 'components.inventoryItem', 'addons'])->where('team_id', $store->id);
        if ($f['query'] ?? null) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$f['query'].'%')->orWhere('sku', 'like', '%'.$f['query'].'%'));
        }
        foreach (['kind' => 'kind', 'categoryId' => 'category_id', 'active' => 'active'] as $key => $column) {
            if (isset($f[$key])) {
                $q->where($column, $f[$key]);
            }
        }
        $page = $q->orderBy('name')->orderBy('id')->paginate(25)->through(fn ($item) => [...CatalogData::item($item), 'availability' => CatalogData::availability($item, $store)]);

        return Inertia::render('products/index', ['business' => InventoryData::business($store), 'catalog' => InventoryData::page($page), 'categories' => Category::where('team_id', $store->id)->orderBy('name')->get()->map(fn ($c) => CatalogData::category($c)), 'filters' => ['query' => $f['query'] ?? '', 'kind' => $f['kind'] ?? null, 'categoryId' => $f['categoryId'] ?? null, 'active' => isset($f['active']) ? (bool) $f['active'] : null]]);
    }

    public function create(Request $request, string $current_team): Response
    {
        return $this->editor($current_team);
    }

    public function edit(Request $request, string $current_team, string $product): Response
    {
        return $this->editor($current_team, $product);
    }

    private function editor(string $slug, ?string $id = null): Response
    {
        $store = Team::where('slug', $slug)->firstOrFail();
        $item = $id ? CatalogItem::where('team_id', $store->id)->findOrFail($id) : null;
        $retained = $item ? RecipeComponent::where('catalog_item_id', $item->id)->pluck('inventory_item_id')->all() : [];
        $retainedAddons = $item ? DB::table('catalog_allowed_addons')->where('catalog_item_id', $item->id)->pluck('addon_id')->all() : [];

        return Inertia::render($item ? 'products/edit' : 'products/create', ['business' => InventoryData::business($store), 'item' => $item ? CatalogData::item($item) : null, 'availability' => $item ? CatalogData::availability($item, $store) : null, 'categories' => Category::where('team_id', $store->id)->where(fn ($q) => $q->where('active', true)->when($item?->category_id, fn ($q) => $q->orWhere('id', $item->category_id)))->orderBy('name')->get()->map(fn ($c) => CatalogData::category($c)), 'inventory' => InventoryItem::where('team_id', $store->id)->whereIn('kind', ['ingredient', 'supply'])->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $retained))->orderBy('name')->get(['id', 'name', 'unit', 'active']), 'addons' => CatalogItem::where('team_id', $store->id)->where('kind', 'addon')->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $retainedAddons))->orderBy('name')->get()->map(fn ($a) => CatalogData::item($a))]);
    }

    public function store(Request $request, string $current_team, SaveCatalogItem $save): RedirectResponse
    {
        return $this->save($request, $current_team, $save);
    }

    public function update(Request $request, string $current_team, string $product, SaveCatalogItem $save): RedirectResponse
    {
        return $this->save($request, $current_team, $save, $product);
    }

    private function save(Request $request, string $slug, SaveCatalogItem $save, ?string $id = null): RedirectResponse
    {
        $store = Team::where('slug', $slug)->firstOrFail();
        $item = $id ? CatalogItem::where('team_id', $store->id)->findOrFail($id) : null;
        $item = $save->handle($store, $request->user(), $request->all(), $item);

        return to_route('products.edit', ['current_team' => $slug, 'product' => $item->id]);
    }
}
