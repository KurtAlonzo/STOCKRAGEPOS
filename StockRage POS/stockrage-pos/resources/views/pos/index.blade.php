<x-app-layout title="POS" wide>
    <x-page-header title="Point of Sale" subtitle="Choose a size, tick the flavors, set how many.">
    </x-page-header>

    <div x-data='pos(@json($catalog), @json(route("pos.checkout")), @json($sizes))' @keydown.escape.window="sizeOpen = false" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_26rem]">

        {{-- ============ Sizes ============ --}}
        <section>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[14rem] flex-1">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input type="search" x-model="search" placeholder="Search a size or flavor…" class="input !pl-9" aria-label="Search">
                </div>
            </div>

            <h3 class="mb-2 text-sm font-bold uppercase tracking-wider text-ink-300">Choose a size</h3>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-2 2xl:grid-cols-4">
                <template x-for="s in shownSizes" :key="s.key">
                    <button type="button" @click="openSize(s)" :disabled="sizeLeft(s) <= 0"
                            class="group relative flex flex-col overflow-hidden rounded-xl border bg-ink-900 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            :class="sizeLeft(s) <= 0 ? 'cursor-not-allowed border-ink-700 opacity-50' : 'border-ink-700 hover:border-brand-600/70 hover:bg-ink-850 active:scale-[.98]'">
                        <div class="relative aspect-[4/3] w-full bg-ink-800">
                            @foreach(\App\Support\MenuArt::KINDS as $kind)
                                <template x-if="s.art === '{{ $kind }}'"><x-takoyaki-art kind="{{ $kind }}" /></template>
                            @endforeach
                            <span x-show="sizeLeft(s) <= 0" class="badge-out absolute left-2 top-2 bg-ink-950/80">Out of stock</span>
                            <span x-show="sizeCount(s) > 0" class="num absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-600 px-2 text-xs font-bold text-white" x-text="sizeCount(s)"></span>
                        </div>
                        <div class="flex flex-1 flex-col gap-1 p-3">
                            <p class="text-base font-bold leading-snug text-fg" x-text="s.label"></p>
                            <p class="num text-xs text-ink-400"><span x-text="s.pcs"></span> pcs · <span x-text="s.mixable ? 'can mix flavors' : 'one flavor'"></span></p>
                            <p class="num text-sm font-bold text-brand-300">from <span x-text="peso(sizeFrom(s))"></span> <span x-show="s.discount > 0" class="ml-1 rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[11px] font-bold text-emerald-300" x-text="'save ' + s.discount + '%'"></span></p>
                        </div>
                    </button>
                </template>
            </div>

            <p x-show="shownSizes.length === 0" x-cloak class="card mt-3 py-12 text-center text-sm text-ink-400">
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
                    <button type="button" x-show="bundles.length" x-cloak @click="clear()" class="btn-ghost btn-sm"><x-icon name="trash" class="h-4 w-4" /> Clear</button>
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
                    <p x-show="bundles.length === 0" class="py-10 text-center text-sm text-ink-400">The order is empty.<br>Choose a size to start.</p>
                    <ul class="divide-y divide-ink-700/60">
                        <template x-for="b in bundles" :key="'b' + b.id">
                            <li class="py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-fg"><span x-text="b.size"></span><span x-show="b.parts.length > 1"> · mixed</span> <span class="num text-xs font-normal text-ink-400" x-text="'(' + b.pcs + ' pcs)'"></span></p>
                                        <ul class="mt-1 space-y-0.5">
                                            <template x-for="x in b.parts" :key="x.product_id">
                                                <li class="num text-xs text-ink-300" x-text="b.parts.length > 1 ? x.pieces + ' × ' + x.flavor : x.flavor"></li>
                                            </template>
                                        </ul>
                                        <p class="num mt-1 text-xs text-ink-400" x-text="peso(b.unitCents / 100) + ' each'"></p>
                                    </div>
                                    <p class="num text-sm font-bold text-fg" x-text="peso(bundleCents(b) / 100)"></p>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden rounded-lg border border-ink-600">
                                        <button type="button" @click="decBundle(b)" class="px-2.5 py-1.5 text-ink-200 hover:bg-ink-700" aria-label="Fewer trays"><x-icon name="minus" class="h-4 w-4" /></button>
                                        <span class="num w-12 text-center text-sm font-bold text-fg" x-text="b.qty"></span>
                                        <button type="button" @click="incBundle(b)" :disabled="!canIncBundle(b)" class="px-2.5 py-1.5 text-ink-200 hover:bg-ink-700 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="More trays"><x-icon name="plus" class="h-4 w-4" /></button>
                                    </div>
                                    <button type="button" @click="removeBundle(b)" class="rounded-lg p-1.5 text-ink-400 hover:bg-ink-700 hover:text-rose-300" aria-label="Remove mixed tray"><x-icon name="trash" class="h-4 w-4" /></button>
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
                            <button type="button" @click="exact()" :disabled="bundles.length === 0" class="rounded-lg bg-ink-700 py-2 text-xs font-bold text-ink-100 hover:bg-ink-600 disabled:opacity-40">Exact</button>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between rounded-lg bg-ink-900 px-3 py-2.5 ring-1 ring-ink-700">
                        <span class="text-sm text-ink-300" x-text="shortBy > 0 && itemCount > 0 ? 'Still needed' : 'Change'"></span>
                        <span class="num text-xl font-bold" :class="shortBy > 0 && itemCount > 0 ? 'text-amber-400' : 'text-emerald-400'"
                              x-text="peso(shortBy > 0 && itemCount > 0 ? shortBy : change)"></span>
                    </div>

                    <button type="button" @click="confirm()" :disabled="!canConfirm" class="btn-primary w-full py-3.5 text-base">
                        <span x-show="!busy" class="inline-flex items-center gap-2"><x-icon name="check" class="h-5 w-5" /> Confirm sale</span>
                        <span x-show="busy" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </aside>

        {{-- ============ Size picker: tick flavors, set how many ============ --}}
        <div x-cloak x-show="sizeOpen" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Choose flavors">
            <div class="absolute inset-0 bg-black/70" @click="closeSize()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl border border-ink-700 bg-ink-900 shadow-2xl sm:rounded-2xl">
                <div class="flex items-start justify-between border-b border-ink-700 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-fg"><span x-text="size ? size.label : ''"></span> <span class="num text-sm font-normal text-ink-400" x-text="size ? '· ' + size.pcs + ' pcs' : ''"></span></h2>
                        <p class="text-sm text-ink-400" x-text="size && size.mixable ? 'Tick a flavor. Tick two or more to mix: the pieces are split evenly. Tap again to remove.' : 'Tick one flavor only. Tap again to remove.'"></p>
                    </div>
                    <button type="button" @click="closeSize()" class="rounded-lg p-2 text-ink-300 hover:bg-ink-800" aria-label="Close"><x-icon name="close" /></button>
                </div>

                <div class="overflow-y-auto px-5 py-4">
                    <ul class="divide-y divide-ink-700/60 rounded-xl border border-ink-700">
                        <template x-for="f in (size ? size.flavors : [])" :key="f.product_id">
                            <li class="flex items-center" :class="isPicked(f) ? 'bg-brand-600/10' : ''">
                                <button type="button" role="checkbox" :aria-checked="isPicked(f)" @click="toggle(f)" :disabled="!flavorOpen(f)"
                                        class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 text-left transition hover:bg-ink-850 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                        :class="isPicked(f) ? 'bg-brand-600/10' : ''">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border-2 transition"
                                          :class="isPicked(f) ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-500'">
                                        <x-icon name="check" class="h-4 w-4" x-show="isPicked(f)" />
                                    </span>
                                    <div class="h-12 w-16 shrink-0 overflow-hidden rounded-lg bg-ink-800">
                                        <template x-if="productById(f.product_id).image"><img :src="productById(f.product_id).image" :alt="f.flavor" class="h-full w-full object-cover"></template>
                                        @foreach(\App\Support\MenuArt::KINDS as $kind)
                                            <template x-if="!productById(f.product_id).image && productById(f.product_id).art === '{{ $kind }}'"><x-takoyaki-art kind="{{ $kind }}" /></template>
                                        @endforeach
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-fg" x-text="f.flavor"></p>
                                        <p class="num text-xs text-ink-400">
                                            <span x-show="f.list_price > f.price" class="line-through opacity-60" x-text="peso(f.list_price)"></span>
                                            <span x-text="peso(f.price) + (size.mixable ? ' per tray' : '')"></span>
                                            · <span :class="productById(f.product_id).stock <= 0 ? 'text-rose-400' : ''" x-text="productById(f.product_id).stock <= 0 ? 'out of stock' : left(productById(f.product_id)) + ' left'"></span>
                                            <span x-show="f.exclusive" class="text-ink-300"> · ready-made mix</span>
                                        </p>
                                    </div>
                                </button>
                                {{-- pieces of this flavor in the mix --}}
                                <div x-show="isMixed && isPicked(f)" x-cloak class="mr-3 flex shrink-0 items-center overflow-hidden rounded-lg border border-ink-600">
                                    <button type="button" @click="bumpPieces(f, -1)" class="px-2 py-1.5 text-ink-200 hover:bg-ink-700" aria-label="Fewer pieces"><x-icon name="minus" class="h-3.5 w-3.5" /></button>
                                    <input type="number" :min="size.min_pieces" :max="size.pcs" x-model.number="pieces[f.product_id]" @change="fixPieces(f)" @focus="$el.select()" class="num w-12 border-0 bg-transparent px-0 py-1 text-center text-sm font-bold text-fg focus:ring-0" inputmode="numeric" :aria-label="'Pieces of ' + f.flavor">
                                    <button type="button" @click="bumpPieces(f, 1)" class="px-2 py-1.5 text-ink-200 hover:bg-ink-700" aria-label="More pieces"><x-icon name="plus" class="h-3.5 w-3.5" /></button>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p x-show="size && size.mixable" class="mt-3 text-xs" :class="limitHit ? 'font-semibold text-amber-400' : 'text-ink-400'" x-text="'Mix: at least ' + size.min_pieces + ' pcs per flavor, up to ' + size.max_flavors + ' flavors.'"></p>
                    <p x-show="size && size.mixable && !isMixed" class="mt-1 text-xs text-ink-400">Tick one flavor, or 2 or more to mix them in one tray.</p>
                    <div x-show="isMixed" x-cloak class="mt-3 flex items-center justify-between gap-3 rounded-lg px-3 py-2 ring-1"
                         :class="piecesOk ? 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30' : 'bg-amber-500/10 text-amber-300 ring-amber-500/30'">
                        <p class="num text-sm font-semibold" x-text="piecesOk ? ('All ' + size.pcs + ' pcs given out') : (remaining > 0 ? (remaining + ' pcs left to give out') : (-remaining + ' pcs too many') + (chosen.some((f) => pieceFor(f) < size.min_pieces) ? ' · min ' + size.min_pieces + ' per flavor' : ''))"></p>
                        <button type="button" @click="splitEvenly()" class="rounded-md bg-ink-700 px-2.5 py-1 text-xs font-bold text-ink-100 hover:bg-ink-600">Split evenly</button>
                    </div>
                </div>

                <div class="space-y-3 border-t border-ink-700 bg-ink-850 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="label !mb-1">How many <span x-text="size && size.mixable ? 'trays' : ''"></span></p>
                            <div class="flex items-center overflow-hidden rounded-lg border border-ink-600">
                                <button type="button" @click="bumpQty(-1)" :disabled="trayQty <= 1" class="px-3 py-2 text-ink-200 hover:bg-ink-700 disabled:opacity-30" aria-label="Fewer"><x-icon name="minus" class="h-4 w-4" /></button>
                                <input type="number" min="1" :max="Math.max(1, trayMax)" x-model.number="trayQty" @change="fixQty()" class="num w-14 border-0 bg-transparent px-0 py-1.5 text-center text-base font-bold text-fg focus:ring-0" inputmode="numeric" aria-label="Quantity">
                                <button type="button" @click="bumpQty(1)" :disabled="trayQty >= trayMax" class="px-3 py-2 text-ink-200 hover:bg-ink-700 disabled:opacity-30" aria-label="More"><x-icon name="plus" class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="num text-xs text-ink-400" x-show="chosen.length > 0" x-text="trayQty > 1 ? peso(unitCents / 100) + ' each' : ''"></p>
                            <p class="num text-2xl font-black text-fg" x-text="peso(trayCents / 100)"></p>
                        </div>
                    </div>
                    <button type="button" @click="addTray()" :disabled="!canAddTray" class="btn-primary w-full py-3">
                        <span x-text="chosen.length === 0 ? 'Tick a flavor first' : (isMixed && !piecesOk ? 'Fix the pieces first' : 'Add to order')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
