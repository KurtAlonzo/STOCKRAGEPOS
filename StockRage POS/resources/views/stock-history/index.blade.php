<x-app-layout title="Stock History">
    <x-page-header title="Stock History" subtitle="A log of every stock change, with the quantity before and after." />

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <div class="w-56">
            <label for="product_id" class="label">Product</label>
            <select id="product_id" name="product_id" class="input">
                <option value="">All products</option>
                @foreach($products as $p)<option value="{{ $p->id }}" @selected((string) request('product_id') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="w-44">
            <label for="type" class="label">Type</label>
            <select id="type" name="type" class="input">
                <option value="">All types</option>
                @foreach($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ ucfirst($t) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="from" class="label">From</label>
            <input id="from" type="date" name="from" value="{{ request('from') }}" class="input">
        </div>
        <div>
            <label for="to" class="label">To</label>
            <input id="to" type="date" name="to" value="{{ request('to') }}" class="input">
        </div>
        <button class="btn-secondary">Filter</button>
        @if(request()->hasAny(['product_id', 'type', 'from', 'to']))
            <a href="{{ route('stock-history.index') }}" class="btn-ghost">Reset</a>
        @endif
    </form>
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                <tr><th>Date &amp; time</th><th>Product</th><th>Type</th><th class="text-right">Change</th><th class="text-right">Previous</th><th class="text-right">New</th><th>Reason</th></tr>
                </thead>
                <tbody>
                @forelse($movements as $m)
                    <tr>
                        <td class="num whitespace-nowrap text-ink-300">{{ $m->created_at->format('M j, Y g:i A') }}</td>
                        <td class="font-medium text-fg">{{ $m->product->name }}</td>
                        <td>
                            @switch($m->type)
                                @case('in') <span class="badge-ok">In</span> @break
                                @case('sale') <span class="badge-info">Sale</span> @break
                                @case('adjustment') <span class="badge-low">Adjustment</span> @break
                                @default <span class="badge-out">Void</span>
                            @endswitch
                        </td>
                        <td @class(['num text-right font-bold', 'text-emerald-400' => $m->qty_change > 0, 'text-rose-400' => $m->qty_change < 0, 'text-ink-400' => $m->qty_change == 0])>{{ $m->qty_change > 0 ? '+' : '' }}{{ $m->qty_change }}</td>
                        <td class="num text-right text-ink-300">{{ $m->previous_qty }}</td>
                        <td class="num text-right font-semibold text-fg">{{ $m->new_qty }}</td>
                        <td class="text-ink-300">
                            @if(preg_match('/Sale #(\d+)/', (string) $m->reason, $mt))
                                {{ trim(str_replace('Sale #'.$mt[1], '', $m->reason)) }}
                                <a href="{{ route('sales.show', $mt[1]) }}" class="font-semibold text-ink-100 underline decoration-ink-500 underline-offset-2 hover:text-brand-300">Sale #{{ $mt[1] }}</a>
                            @else
                                {{ $m->reason ?: '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-14 text-center text-ink-400">No stock movements found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
</x-app-layout>
