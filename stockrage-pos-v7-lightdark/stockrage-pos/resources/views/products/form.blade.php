@php($editing = $product->exists)
<x-app-layout :title="$editing ? 'Edit product' : 'Add product'">
    <x-page-header :title="$editing ? 'Edit product' : 'Add product'">
        <a href="{{ route('products.index') }}" class="btn-ghost">← Back to products</a>
    </x-page-header>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
          x-data="{ preview: @js($product->image_url), removed: false }"
          class="card card-pad max-w-3xl">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="255" class="input" placeholder="e.g. Classic Takoyaki" autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>

            <div>
                <label class="label" for="category">Category</label>
                <input id="category" name="category" value="{{ old('category', $product->category) }}" list="categories" maxlength="100" class="input" placeholder="e.g. Takoyaki">
                <datalist id="categories">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                <x-input-error :messages="$errors->get('category')" class="mt-1.5" />
            </div>

            <div>
                <label class="label" for="price">Price (₱)</label>
                <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $product->price) }}" required class="input num" placeholder="0.00">
                <x-input-error :messages="$errors->get('price')" class="mt-1.5" />
            </div>

            <div>
                <label class="label" for="cost_price">Cost per piece (₱)</label>
                <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price) }}" class="input num" placeholder="0.00">
                <p class="hint">What one serving costs you. Used for the profit numbers in Reports and the Dashboard.</p>
                <x-input-error :messages="$errors->get('cost_price')" class="mt-1.5" />
            </div>

            <div>
                @if($editing)
                    <label class="label">Current stock</label>
                    <p class="num flex h-[38px] items-center rounded-lg border border-ink-700 bg-ink-850 px-3 text-sm font-semibold text-fg">{{ $product->stock }}</p>
                    <p class="hint">Change stock with <em>Add stock</em> or <em>Adjust</em> on the Products page, so it shows in Stock History.</p>
                @else
                    <label class="label" for="stock">Starting stock</label>
                    <input id="stock" name="stock" type="number" step="1" min="0" value="{{ old('stock', 0) }}" class="input num">
                    <x-input-error :messages="$errors->get('stock')" class="mt-1.5" />
                @endif
            </div>

            <div>
                <label class="label" for="low_stock_threshold">Low stock alert at</label>
                <input id="low_stock_threshold" name="low_stock_threshold" type="number" step="1" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" required class="input num">
                <p class="hint">Shows LOW STOCK when stock is at or below this number.</p>
                <x-input-error :messages="$errors->get('low_stock_threshold')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="image">Image (optional, max 2 MB)</label>
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-ink-800 text-ink-500 ring-1 ring-ink-600">
                        <img x-show="preview && !removed" :src="preview" alt="" class="h-full w-full object-cover" x-cloak>
                        <span x-show="!preview || removed"><x-icon name="image" class="h-7 w-7" /></span>
                    </div>
                    <div class="space-y-2">
                        <input id="image" name="image" type="file" accept="image/*"
                               @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removed = false }"
                               class="block w-full text-sm text-ink-300 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-fg hover:file:bg-ink-600">
                        @if($editing && $product->image)
                            <label class="flex items-center gap-2 text-sm text-ink-300">
                                <input type="hidden" name="remove_image" value="0">
                                <input type="checkbox" name="remove_image" value="1" x-model="removed" class="rounded border-ink-600 bg-ink-850 text-brand-600 focus:ring-brand-500 focus:ring-offset-ink-900">
                                Remove current image
                            </label>
                        @endif
                    </div>
                </div>
                <x-input-error :messages="$errors->get('image')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-2">
                <label class="flex items-center gap-2 text-sm font-medium text-ink-100">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-ink-600 bg-ink-850 text-brand-600 focus:ring-brand-500 focus:ring-offset-ink-900">
                    Active — show this product in the POS
                </label>
            </div>
        </div>

        <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-ink-700 pt-5">
            <div>
                @if($editing)
                    <button type="submit" form="delete-form" class="btn-danger" onclick="return confirm('Delete {{ addslashes($product->name) }}? This cannot be undone.')">
                        <x-icon name="trash" class="h-4 w-4" /> Delete
                    </button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('products.index') }}" class="btn-ghost">Cancel</a>
                <button class="btn-primary">{{ $editing ? 'Save changes' : 'Add product' }}</button>
            </div>
        </div>
    </form>

    @if($editing)
        <form id="delete-form" method="POST" action="{{ route('products.destroy', $product) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif
</x-app-layout>
