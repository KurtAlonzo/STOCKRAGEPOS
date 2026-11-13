<x-app-layout :title="'Sale #'.$sale->id">
    <x-page-header :title="'Sale #'.$sale->id" :subtitle="$sale->created_at->format('l, F j, Y · g:i A')">
        <a href="{{ route('sales.index') }}" class="btn-ghost">← Sales history</a>
        <a href="{{ route('sales.receipt', ['sale' => $sale, 'print' => 1]) }}" target="_blank" class="btn-secondary"><x-icon name="print" class="h-4 w-4" /> Print receipt</a>
    </x-page-header>

    <div x-data="{ confirmVoid: false }" @keydown.escape.window="confirmVoid = false" class="grid max-w-5xl gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">

        {{-- Items --}}
        <div class="card overflow-hidden print:border-0 print:bg-white print:text-black">
            <div class="flex items-center justify-between border-b border-ink-700 px-5 py-4">
                <p class="section-title">Items</p>
                @if($sale->isVoided())<span class="badge-muted">Voided {{ $sale->voided_at?->format('M j, g:i A') }}</span>@else<span class="badge-ok">Completed</span>@endif
            </div>
            <table class="tbl">
                <thead><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td class="font-medium text-fg">{{ $item->product->name }}</td>
                        <td class="num text-right">{{ $item->qty }}</td>
                        <td class="num text-right text-ink-300">₱{{ number_format($item->price, 2) }}</td>
                        <td class="num text-right font-semibold text-fg">₱{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Summary --}}
        <div class="space-y-4">
            <div class="card card-pad space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-ink-400">Cashier</span><span class="font-medium text-fg">{{ $sale->cashier?->name ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-ink-400">Payment method</span><span class="font-medium capitalize text-fg">{{ $sale->payment_method }}</span></div>
                <div class="flex justify-between"><span class="text-ink-400">Total</span><span class="num text-lg font-bold text-fg">₱{{ number_format($sale->total, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-400">Amount paid</span><span class="num text-fg">₱{{ number_format($sale->amount_paid, 2) }}</span></div>
                <div class="flex justify-between border-t border-ink-700 pt-3"><span class="text-ink-400">Change</span><span class="num font-semibold text-emerald-400">₱{{ number_format($sale->change_amount, 2) }}</span></div>
            </div>

            @unless($sale->isVoided())
                <button type="button" @click="confirmVoid = true" class="btn-danger w-full print:hidden"><x-icon name="undo" class="h-4 w-4" /> Void this sale</button>
            @else
                <p class="rounded-lg border border-ink-700 bg-ink-850 px-4 py-3 text-sm text-ink-300 print:hidden">This sale was voided{{ $sale->voidedBy ? ' by '.$sale->voidedBy->name : '' }}. Its stock was returned and it is not counted in reports.@if($sale->void_reason)<span class="mt-2 block text-ink-200">Reason: {{ $sale->void_reason }}</span>@endif</p>
            @endunless
        </div>

        {{-- Void confirm dialog (only for sales that can still be voided) --}}
        @unless($sale->isVoided())
        <div x-cloak x-show="confirmVoid" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="alertdialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/70" @click="confirmVoid = false"></div>
            <form method="POST" action="{{ route('sales.void', $sale) }}" x-show="confirmVoid" x-transition class="card relative w-full max-w-md p-6 shadow-2xl shadow-black/60">
                @csrf
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-600/15 text-rose-400"><x-icon name="warning" class="h-6 w-6" /></span>
                <h2 class="mt-4 text-lg font-bold text-fg">Void this sale?</h2>
                <p class="mt-1 text-sm text-ink-300">Void this sale? Stock will be returned. Sale #{{ $sale->id }} will no longer count in the dashboard or reports.</p>
                <label for="void_reason" class="label mt-4">Reason (optional)</label>
                <input id="void_reason" name="reason" maxlength="200" class="input" placeholder="e.g. Wrong order, customer cancelled">
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="confirmVoid = false">Keep sale</button>
                    <button class="btn-primary">Yes, void sale</button>
                </div>
            </form>
        </div>
        @endunless
    </div>
</x-app-layout>
