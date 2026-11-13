<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $query = Product::query()->orderBy('name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        match ($request->query('status')) {
            'low' => $query->active()->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
            'out' => $query->active()->where('stock', '<=', 0),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        return view('products.index', [
            'products' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => new Product([
                'is_active' => true,
                'stock' => 0,
                'low_stock_threshold' => (int) Setting::get('default_low_stock_threshold', 10),
            ]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(creating: true));

        DB::transaction(function () use ($request, $data) {
            $product = Product::create([
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'price' => $data['price'],
                'cost_price' => $data['cost_price'] ?? 0,
                'stock' => 0,
                'low_stock_threshold' => $data['low_stock_threshold'],
                'is_active' => $request->boolean('is_active'),
                'image' => $request->hasFile('image')
                    ? $request->file('image')->store('products', 'public')
                    : null,
            ]);

            // Starting stock also goes through StockService so it shows in Stock History.
            if (($data['stock'] ?? 0) > 0) {
                $this->stock->change($product, (int) $data['stock'], 'in', 'Starting stock');
            }
        });

        return redirect()->route('products.index')->with('success', "{$data['name']} was added.");
    }

    public function edit(Product $product): View
    {
        return view('products.form', [
            'product' => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate($this->rules(creating: false));

        $attributes = [
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'price' => $data['price'],
            'cost_price' => $data['cost_price'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'],
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            $this->deleteImage($product);
            $attributes['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($product);
            $attributes['image'] = null;
        }

        // Note: stock is NOT editable here. Use Add Stock / Adjust Stock so it is recorded.
        $product->update($attributes);

        return redirect()->route('products.index')->with('success', "{$product->name} was updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->saleItems()->exists()) {
            return back()->with('error', "{$product->name} has sales history and cannot be deleted. Mark it inactive instead.");
        }

        $this->deleteImage($product);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->name.' is now '.($product->is_active ? 'active' : 'inactive').'.');
    }

    public function addStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->stock->change($product, (int) $data['quantity'], 'in', $data['reason'] ?? 'Stock added');

        return back()->with('success', "Added {$data['quantity']} to {$product->name}. New stock: {$product->stock}.");
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'new_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $changed = DB::transaction(function () use ($data, $product) {
            $current = Product::query()->lockForUpdate()->findOrFail($product->id);
            $delta = (int) $data['new_quantity'] - $current->stock;

            if ($delta === 0) {
                return false;
            }

            $this->stock->change($current, $delta, 'adjustment', $data['reason']);
            $product->setRawAttributes($current->getAttributes(), true);

            return true;
        });

        if (! $changed) {
            return back()->with('error', "{$product->name} already has that quantity. Nothing changed.");
        }

        return back()->with('success', "{$product->name} stock adjusted to {$product->stock}.");
    }

    private function rules(bool $creating): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => $creating ? ['nullable', 'integer', 'min:0', 'max:1000000'] : ['prohibited'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'image' => ['nullable', 'image', 'max:2048'], // 2 MB
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function categories()
    {
        return Product::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
    }
}
