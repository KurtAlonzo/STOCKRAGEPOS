@php($pageIds = $products->pluck('id')->map(fn ($id) => (string) $id)->values()->all())
<x-app-layout title="Products">
    <x-page-header title="Products" subtitle="Manage your menu and stock levels. Every stock change is recorded.">
        <a href="{{ route('products.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add product</a>
    </x-page-header>

    <div x-data="{
            modal: null,           // 'add' | 'adjust' | null
            p: { id: null, name: '', stock: 0, addUrl: '', adjustUrl: '' },
            open(kind, product) { this.p = product; this.modal = kind; this.$nextTick(() => this.$refs[kind + 'Input']?.focus()); },
            close() { this.modal = null; },
            ids: [],               // ticked product ids
            pageIds: @js($pageIds),
            get allTicked() { return this.pageIds.length ? this.pageIds.every(function (id) { return this.ids.includes(id); }, this) : false; },
            toggleAll() { this.ids = this.allTicked ? [] : this.pageIds.slice(); },
        }" @keydown.escape.window="close()">

        {{-- Filters --}}
        <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <label for="q" class="label">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Name or category" class="input">
            </div>
            <div class="w-48">
                <label for="status" class="label">Show</label>
                <select id="status" name="status" class="input">
                    <option value="">All products</option>
                    <option value="low" @selected(request('status') === 'low')>Low stock</option>
                    <option value="out" @selected(request('status') === 'out')>Out of stock</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <button class="btn-secondary">Filter</button>
            @if(request()->hasAny(['q', 'status']))
                <a href="{{ route('products.index') }}" class="btn-ghost">Reset</a>
            @endif
        </form>

        {{-- Bulk bar: tick products, type one quantity, add it to all of them --}}
        <form id="bulk-form" method="POST" action="{{ route('products.bulk-add-stock') }}"
              class="card card-pad mb-4 flex flex-wrap items-end gap-x-4 gap-y-3 transition"
              :class="ids.length ? 'border-brand-600/60' : ''">
            @csrf
            <div>
                <p class="label">Add stock to many products</p>
                <p class="text-sm text-ink-300"><span class="num font-bold text-fg" x-text="ids.length"></span> selected<span x-show="!ids.length" class="text-ink-400"> · check the boxes in the list below</span></p>
            </div>
            <div class="w-32">
                <label class="label" for="bulk_quantity">Quantity</label>
                <input id="bulk_quantity" type="number" name="quantity" min="1" step="1" required class="input num" placeholder="e.g. 50">
            </div>
            <div class="min-w-[12rem] flex-1">
                <label class="label" for="bulk_reason">Note (optional)</label>
                <input id="bulk_reason" type="text" name="reason" maxlength="255" class="input" placeholder="e.g. Delivery from supplier">
            </div>
            <button class="btn-primary" :disabled="!ids.length"><x-icon name="plus" class="h-4 w-4" /> Add stock to <span class="num" x-text="ids.length"></span></button>
        </form>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th class="w-10"><input type="checkbox" @change="toggleAll()" :checked="allTicked" aria-label="Select all products on this page" class="h-4 w-4 rounded border-ink-500 bg-ink-850 text-brand-600 focus:ring-brand-500"></th>
                        <th>Product</th><th>Category</th><th class="text-right">Price</th><th class="text-right">Stock</th><th>Status</th><th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr @class(['opacity-60' => ! $product->is_active])>
                            <td><input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk-form" x-model="ids" aria-label="Select {{ $product->name }}" class="h-4 w-4 rounded border-ink-500 bg-ink-850 text-brand-600 focus:ring-brand-500"></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="" class="h-11 w-11 rounded-lg object-cover ring-1 ring-ink-600">
                                    @else
                                        <span class="block h-11 w-11 overflow-hidden rounded-lg ring-1 ring-ink-700"><x-takoyaki-art :name="$product->name" /></span>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-fg">{{ $product->name }}</p>
                                        @unless($product->is_active)<span class="badge-muted mt-0.5">Inactive</span>@endunless
                                    </div>
                                </div>
                            </td>
                            <td class="text-ink-300">{{ $product->category ?: '—' }}</td>
                            <td class="num text-right text-fg">₱{{ number_format($product->price, 2) }}</td>
                            <td class="num text-right">
                                <span class="font-semibold text-fg">{{ $product->stock }}{{ $product->stock_flavor || $product->pieces ? ' pcs' : '' }}</span>
                                <span class="block text-[11px] text-ink-500">alert ≤ {{ $product->low_stock_threshold }}</span>
                            </td>
                            <td><x-status-badge :product="$product" /></td>
                            <td>
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <button type="button" class="btn-secondary btn-sm"
                                        @click="open('add', @js(['id' => $product->id, 'name' => $product->name, 'stock' => $product->stock, 'addUrl' => route('products.add-stock', $product), 'adjustUrl' => route('products.adjust', $product)]))">
                                        <x-icon name="plus" class="h-3.5 w-3.5" /> Add stock
                                    </button>
                                    <button type="button" class="btn-secondary btn-sm"
                                        @click="open('adjust', @js(['id' => $product->id, 'name' => $product->name, 'stock' => $product->stock, 'addUrl' => route('products.add-stock', $product), 'adjustUrl' => route('products.adjust', $product)]))">
                                        Adjust
                                    </button>
                                    <a href="{{ route('products.edit', $product) }}" class="btn-ghost btn-sm" title="Edit"><x-icon name="edit" class="h-4 w-4" /> Edit</a>
                                    <form method="POST" action="{{ route('products.toggle', $product) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn-ghost btn-sm" title="{{ $product->is_active ? 'Hide from POS' : 'Show in POS' }}">
                                            {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-14 text-center text-ink-400">
                            No products found.
                            <a href="{{ route('products.create') }}" class="font-semibold text-brand-400 hover:text-brand-300">Add your first product</a>.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $products->links() }}</div>

        @if($menuPrices->isNotEmpty())
            <details class="card card-pad mt-4">
                <summary class="cursor-pointer font-semibold">Menu prices by size</summary>
                @foreach($menuPrices as $item)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <span>{{ $item->name }} · ₱{{ number_format($item->price, 2) }}</span>
                        <a class="btn-secondary btn-sm" href="{{ route('products.edit', $item) }}">Edit</a>
                    </div>
                @endforeach
            </details>
        @endif

        {{-- Add stock modal --}}
        <div x-cloak x-show="modal === 'add'" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <form method="POST" :action="p.addUrl" x-show="modal === 'add'" x-transition class="card relative w-full max-w-md p-6 shadow-2xl shadow-black/60">
                @csrf
                <h2 class="text-lg font-bold text-fg">Add stock</h2>
                <p class="mt-1 text-sm text-ink-400"><span class="font-semibold text-ink-200" x-text="p.name"></span> · currently <span class="num font-semibold text-ink-200" x-text="p.stock"></span> in stock</p>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label" for="add_quantity">Quantity to add</label>
                        <input id="add_quantity" x-ref="addInput" type="number" name="quantity" min="1" step="1" required class="input" placeholder="e.g. 50">
                    </div>
                    <div>
                        <label class="label" for="add_reason">Note (optional)</label>
                        <input id="add_reason" type="text" name="reason" maxlength="255" class="input" placeholder="e.g. Delivery from supplier">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="close()">Cancel</button>
                    <button class="btn-primary">Add stock</button>
                </div>
            </form>
        </div>

        {{-- Adjust stock modal --}}
        <div x-cloak x-show="modal === 'adjust'" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <form method="POST" :action="p.adjustUrl" x-show="modal === 'adjust'" x-transition class="card relative w-full max-w-md p-6 shadow-2xl shadow-black/60">
                @csrf
                <h2 class="text-lg font-bold text-fg">Adjust stock</h2>
                <p class="mt-1 text-sm text-ink-400">Set the real count for <span class="font-semibold text-ink-200" x-text="p.name"></span>. Currently <span class="num font-semibold text-ink-200" x-text="p.stock"></span>.</p>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label" for="adj_quantity">New quantity</label>
                        <input id="adj_quantity" x-ref="adjustInput" type="number" name="new_quantity" min="0" step="1" required class="input" :value="p.stock">
                    </div>
                    <div>
                        <label class="label" for="adj_reason">Reason (required)</label>
                        <input id="adj_reason" type="text" name="reason" required maxlength="255" class="input" placeholder="e.g. Counted again, spoiled, damaged">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="close()">Cancel</button>
                    <button class="btn-primary">Save adjustment</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif
</x-app-layout>
