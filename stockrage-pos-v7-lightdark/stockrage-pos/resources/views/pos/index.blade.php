<x-app-layout title="POS" wide>
    <x-page-header title="Point of Sale" subtitle="Choose a size and quantity to add it to the order.">
    </x-page-header>

    <div x-data="pos({{ Js::from($products) }}, {{ Js::from(route('pos.checkout')) }})" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_26rem]">

        {{-- ============ Product grid ============ --}}
        <section>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[14rem] flex-1">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input type="search" x-model="search" placeholder="Search products…" class="input !pl-9" aria-label="Search products">
                </div>
                <div class="flex flex-wrap gap-1.5" x-show="categories.length > 2">
                    <template x-for="c in categories" :key="c">
                        <button type="button" @click="category = c"
                                class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition"
                                :class="category === c ? 'bg-brand-600 text-white' : 'bg-ink-800 text-ink-300 hover:bg-ink-700'" x-text="c"></button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                <template x-for="group in catalog" :key="group.key">
                    <div>
                        <template x-if="group.sizes[0].label">
                            <form class="card flex h-full flex-col gap-3 p-4"
                                  x-data="{ sizeId: '', qty: 1, get selected() { return group.sizes.find(({ product }) => String(product.id) === this.sizeId)?.product }, get canAdd() { return this.selected && Number.isInteger(this.qty) && this.qty > 0 && this.qty <= this.left(this.selected) && !this.busy } }"
                                  @submit.prevent="if (canAdd) { add(selected, qty); sizeId = ''; qty = 1 }">
                                <h2 class="font-semibold text-fg" x-text="group.name"></h2>
                                <label class="block text-sm">
                                    Size
                                    <select class="input mt-1 w-full" x-model="sizeId" @change="qty = 1" required>
                                        <option value="">Choose a size</option>
                                        <template x-for="size in group.sizes" :key="size.product.id">
                                            <option :value="String(size.product.id)" :disabled="left(size.product) <= 0"
                                                    x-text="size.label + ' · ' + peso(size.product.price) + (left(size.product) <= 0 ? ' · Out of stock' : ' · ' + left(size.product) + ' left')"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    Quantity
                                    <input type="number" class="input mt-1 w-full" min="1" step="1"
                                           :max="selected ? left(selected) : null" x-model.number="qty" required>
                                </label>
                                <p class="num text-sm" aria-live="polite" x-show="selected"
                                   x-text="selected && Number.isInteger(qty) && qty > 0 ? peso(selected.price) + ' × ' + qty + ' = ' + peso(Math.round(selected.price * 100) * qty / 100) : ''"></p>
                                <button type="submit" class="btn-primary mt-auto disabled:opacity-50" :disabled="!canAdd">Add to cart</button>
                            </form>
                        </template>
                        <template x-if="!group.sizes[0].label">
                            <div x-data="{ p: group.sizes[0].product }">
                            <button type="button" @click="add(p)" :disabled="p.stock <= 0"
                                    class="group relative flex flex-col overflow-hidden rounded-xl border bg-ink-900 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                    :class="p.stock <= 0 ? 'cursor-not-allowed border-ink-700 opacity-50' : 'border-ink-700 hover:border-brand-600/70 hover:bg-ink-850 active:scale-[.98]'">
                                <div class="relative aspect-[4/3] w-full bg-ink-800">
                                    <template x-if="p.image"><img :src="p.image" :alt="p.name" class="h-full w-full object-cover"></template>
                                    <template x-if="!p.image"><x-takoyaki-art class="h-full w-full" /></template>
                                    <span x-show="p.stock <= 0" class="badge-out absolute left-2 top-2 bg-ink-950/80">Out of stock</span>
                                    <span x-show="isLow(p)" class="badge-low absolute left-2 top-2 bg-ink-950/80">Low</span>
                                    <span x-show="inCart(p) > 0" class="num absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-600 px-2 text-xs font-bold text-white" x-text="inCart(p)"></span>
                                </div>
                                <div class="flex flex-1 flex-col justify-between gap-1 p-3">
                                    <p class="text-sm font-semibold leading-snug text-fg" x-text="p.name"></p>
                                    <div class="flex items-end justify-between">
                                        <p class="num text-base font-bold text-brand-300" x-text="peso(p.price)"></p>
                                        <p class="num text-xs" :class="p.stock <= 0 ? 'text-rose-400' : (isLow(p) ? 'text-amber-400' : 'text-ink-400')"
                                           x-text="p.stock <= 0 ? 'None left' : p.stock + ' left'"></p>
                                    </div>
                                </div>
                            </button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <p x-show="filtered.length === 0" x-cloak class="card mt-3 py-12 text-center text-sm text-ink-400">
                No products match. @if($products->isEmpty()) <a href="{{ route('products.create') }}" class="font-semibold text-brand-400">Add a product</a> first. @endif
            </p>
        </section>

        {{-- ============ Cart ============ --}}
        <aside class="xl:sticky xl:top-6 xl:self-start">
            <div class="card flex flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-ink-700 px-5 py-4">
                    <div>
                        <h2 class="text-base font-bold text-fg">Current order</h2>
                        <p class="num text-xs text-ink-400"><span x-text="itemCount"></span> item<span x-show="itemCount !== 1">s</span></p>
                    </div>
                    <button type="button" x-show="cart.length" x-cloak @click="clear()" class="btn-ghost btn-sm"><x-icon name="trash" class="h-4 w-4" /> Clear</button>
                </div>

                {{-- Result message --}}
                <template x-if="message">
                    <div class="mx-5 mt-4 flex items-start justify-between gap-3 rounded-lg border px-3 py-2.5 text-sm font-medium"
                         :class="message.type === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300'" role="status">
                        <span>
                            <span x-text="message.text"></span>
                            <a x-show="message.saleId" :href="'{{ url('/sales') }}/' + message.saleId" class="ml-1 underline underline-offset-2">View</a>
                            <a x-show="message.saleId" :href="'{{ url('/sales') }}/' + message.saleId + '/receipt?print=1'" target="_blank" class="ml-2 font-bold underline underline-offset-2">Print receipt</a>
                        </span>
                        <button type="button" @click="message = null" class="opacity-70 hover:opacity-100" aria-label="Dismiss"><x-icon name="close" class="h-4 w-4" /></button>
                    </div>
                </template>

                {{-- Items --}}
                <div class="max-h-[40vh] min-h-[9rem] overflow-y-auto px-5 py-2">
                    <p x-show="cart.length === 0" class="py-10 text-center text-sm text-ink-400">The order is empty.<br>Tap a product to start.</p>
                    <ul class="divide-y divide-ink-700/60">
                        <template x-for="item in cart" :key="item.product_id">
                            <li class="py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-fg" x-text="item.name"></p>
                                        <p class="num text-xs text-ink-400" x-text="peso(item.price) + ' each'"></p>
                                    </div>
                                    <p class="num text-sm font-bold text-fg" x-text="peso(item.price * item.qty)"></p>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden rounded-lg border border-ink-600">
                                        <button type="button" @click="dec(item)" class="px-2.5 py-1.5 text-ink-200 hover:bg-ink-700" aria-label="Decrease"><x-icon name="minus" class="h-4 w-4" /></button>
                                        <span class="num w-9 text-center text-sm font-bold text-fg" x-text="item.qty"></span>
                                        <button type="button" @click="inc(item)" :disabled="item.qty >= stockOf(item)" class="px-2.5 py-1.5 text-ink-200 hover:bg-ink-700 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="Increase"><x-icon name="plus" class="h-4 w-4" /></button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span x-show="item.qty >= stockOf(item)" class="text-[11px] font-semibold text-amber-400">Max stock</span>
                                        <button type="button" @click="remove(item)" class="rounded-lg p-1.5 text-ink-400 hover:bg-ink-700 hover:text-rose-300" aria-label="Remove"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Payment --}}
                <div class="space-y-4 border-t border-ink-700 bg-ink-850 px-5 py-4">
                    <div class="flex items-baseline justify-between">
                        <span class="section-title">Total</span>
                        <span class="num text-3xl font-black text-fg" x-text="peso(total)"></span>
                    </div>

                    <div>
                        <label class="label" for="method">Payment method</label>
                        <select id="method" x-model="method" class="input">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="card">Card</option>
                        </select>
                    </div>

                    <div>
                        <label class="label" for="paid">Amount paid</label>
                        <input id="paid" type="number" min="0" step="0.01" x-model.number="paid" @focus="$el.select()" class="input num text-lg font-bold" inputmode="decimal">
                        <div class="mt-2 grid grid-cols-5 gap-1.5">
                            <template x-for="amt in [50, 100, 200, 500]" :key="amt">
                                <button type="button" @click="quick(amt)" class="num rounded-lg bg-ink-700 py-2 text-xs font-bold text-ink-100 hover:bg-ink-600" x-text="'₱' + amt"></button>
                            </template>
                            <button type="button" @click="exact()" :disabled="cart.length === 0" class="rounded-lg bg-ink-700 py-2 text-xs font-bold text-ink-100 hover:bg-ink-600 disabled:opacity-40">Exact</button>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between rounded-lg bg-ink-900 px-3 py-2.5 ring-1 ring-ink-700">
                        <span class="text-sm text-ink-300" x-text="shortBy > 0 && cart.length ? 'Still needed' : 'Change'"></span>
                        <span class="num text-xl font-bold" :class="shortBy > 0 && cart.length ? 'text-amber-400' : 'text-emerald-400'"
                              x-text="peso(shortBy > 0 && cart.length ? shortBy : change)"></span>
                    </div>

                    <button type="button" @click="confirm()" :disabled="!canConfirm" class="btn-primary w-full py-3.5 text-base">
                        <span x-show="!busy" class="inline-flex items-center gap-2"><x-icon name="check" class="h-5 w-5" /> Confirm sale</span>
                        <span x-show="busy" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
