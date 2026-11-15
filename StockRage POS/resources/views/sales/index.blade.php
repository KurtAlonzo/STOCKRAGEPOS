<x-app-layout title="Sales History">
    <x-page-header title="Sales History" subtitle="Every saved sale. Search by sale number or filter by date." />

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[12rem] flex-1">
            <label for="search" class="label">Sale ID</label>
            <input id="search" name="search" value="{{ request('search') }}" placeholder="e.g. 25" class="input num" inputmode="numeric">
        </div>
        <div>
            <label for="from" class="label">From</label>
            <input id="from" type="date" name="from" value="{{ request('from') }}" class="input">
        </div>
        <div>
            <label for="to" class="label">To</label>
            <input id="to" type="date" name="to" value="{{ request('to') }}" class="input">
        </div>
        <div>
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="input">
                <option value="">All</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="voided" @selected(request('status') === 'voided')>Voided</option>
            </select>
        </div>
        <button class="btn-secondary">Filter</button>
        @if(request()->hasAny(['search', 'from', 'to', 'status']))
            <a href="{{ route('sales.index') }}" class="btn-ghost">Reset</a>
        @endif
    </form>
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                <tr><th>ID</th><th>Date &amp; time</th><th>Products sold</th><th class="text-right">Qty</th><th class="text-right">Total</th><th>Payment</th><th>Cashier</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr @class(['opacity-60' => $sale->isVoided()])>
                        <td><a href="{{ route('sales.show', $sale) }}" class="num font-semibold text-fg hover:text-brand-300">#{{ $sale->id }}</a></td>
                        <td class="num whitespace-nowrap text-ink-300">{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                        <td class="max-w-xs truncate text-ink-300" title="{{ $sale->items->map(fn ($i) => $i->product->name.' ×'.$i->qty)->implode(', ') }}">
                            {{ $sale->items->map(fn ($i) => $i->product->name.' ×'.$i->qty)->implode(', ') }}
                        </td>
                        <td class="num text-right text-ink-300">{{ $sale->items->sum('qty') }}</td>
                        <td @class(['num text-right font-semibold', 'text-fg' => ! $sale->isVoided(), 'text-ink-400 line-through' => $sale->isVoided()])>₱{{ number_format($sale->total, 2) }}</td>
                        <td class="capitalize text-ink-300">{{ $sale->payment_method }}</td>
                        <td class="whitespace-nowrap text-ink-300">{{ $sale->cashier?->name ?? '—' }}</td>
                        <td>@if($sale->isVoided())<span class="badge-muted">Voided</span>@else<span class="badge-ok">Completed</span>@endif</td>
                        <td class="text-right"><a href="{{ route('sales.show', $sale) }}" class="btn-ghost btn-sm">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-14 text-center text-ink-400">No sales found for these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $sales->links() }}</div>
</x-app-layout>
