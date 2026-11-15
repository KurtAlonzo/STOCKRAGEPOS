<x-app-layout title="Reports">
    <x-page-header title="Reports" subtitle="Sales summaries from completed sales only (voided sales are excluded).">
        <a href="{{ route('reports.export', ['period' => $period, 'from' => $from, 'to' => $to]) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> Export CSV</a>
    </x-page-header>

    {{-- Period tabs --}}
    <div class="mb-4 inline-flex rounded-xl border border-ink-700 bg-ink-900 p-1" role="tablist">
        @foreach(['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
            <a href="{{ route('reports.index', ['period' => $key]) }}" role="tab" aria-selected="{{ $period === $key ? 'true' : 'false' }}"
               @class(['rounded-lg px-5 py-2 text-sm font-semibold transition', 'bg-brand-600 text-white' => $period === $key, 'text-ink-300 hover:text-fg' => $period !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="period" value="{{ $period }}">
        <div>
            <label for="from" class="label">From</label>
            <input id="from" type="date" name="from" value="{{ $from }}" class="input">
        </div>
        <div>
            <label for="to" class="label">To</label>
            <input id="to" type="date" name="to" value="{{ $to }}" class="input">
        </div>
        <button class="btn-primary">Generate</button>
    </form>
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <div class="card card-pad"><p class="section-title">Total sales</p><p class="num mt-2 text-3xl font-bold text-fg">₱{{ number_format($totalSales, 2) }}</p></div>
        <div class="card card-pad"><p class="section-title">Profit</p><p class="num mt-2 text-3xl font-bold {{ $totalProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">₱{{ number_format($totalProfit, 2) }}</p><p class="mt-1 text-xs text-ink-400">Sales minus product cost</p></div>
        <div class="card card-pad"><p class="section-title">Total orders</p><p class="num mt-2 text-3xl font-bold text-fg">{{ number_format($totalOrders) }}</p></div>
    </div>

    <p class="mb-3 text-xs text-ink-400">Profit = selling price minus the cost per piece saved on each product at the time of the sale. Products with no cost set count as ₱0 cost.</p>

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="card card-pad lg:col-span-3">
            <p class="section-title">{{ ucfirst($period) }} sales</p>
            <div class="mt-4 h-72"><canvas id="reportChart"></canvas></div>
        </div>

        <div class="card overflow-hidden lg:col-span-2">
            <div class="max-h-[26rem] overflow-y-auto">
                <table class="tbl">
                    <thead class="sticky top-0 bg-ink-900"><tr><th>Period</th><th class="text-right">Orders</th><th class="text-right">Total</th><th class="text-right">Profit</th></tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="text-ink-200">{{ $row['label'] }}</td>
                            <td class="num text-right text-ink-300">{{ $row['orders'] }}</td>
                            <td class="num text-right font-semibold text-fg">₱{{ number_format($row['total'], 2) }}</td>
                            <td class="num text-right text-emerald-400">₱{{ number_format($row['profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-12 text-center text-ink-400">No completed sales in this range.</td></tr>
                    @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                        <tfoot class="sticky bottom-0 bg-ink-850">
                        <tr class="border-t border-ink-600">
                            <td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-ink-300">Total</td>
                            <td class="num px-4 py-3 text-right font-bold text-fg">{{ $totalOrders }}</td>
                            <td class="num px-4 py-3 text-right font-bold text-fg">₱{{ number_format($totalSales, 2) }}</td>
                            <td class="num px-4 py-3 text-right font-bold text-emerald-400">₱{{ number_format($totalProfit, 2) }}</td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = @json($rows->map(fn ($r) => ['label' => $r['label'], 'total' => $r['total']])->values());
            const isLine = rows.length > 12;
            new Chart(document.getElementById('reportChart'), {
                type: isLine ? 'line' : 'bar',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        label: 'Sales', data: rows.map(r => r.total), borderColor: brandColor(),
                        backgroundColor: isLine ? brandColor(.18) : brandColor(), fill: isLine, tension: .3,
                        borderRadius: 6, maxBarThickness: 48, pointRadius: isLine ? 2 : 0, borderWidth: isLine ? 2 : 0,
                    }],
                },
                options: {
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') }, grid: { color: inkColor(700) } },
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8, callback(v) { return String(this.getLabelForValue(v)).replace(/ \(.+\)$/, ''); } } },
                    },
                    plugins: { tooltip: { callbacks: { label: c => ' ' + peso(c.parsed.y) } } },
                },
            });
        });
    </script>
    @endpush
</x-app-layout>
