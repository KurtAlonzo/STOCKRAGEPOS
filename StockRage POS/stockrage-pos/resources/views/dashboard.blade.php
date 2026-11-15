<x-app-layout title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="now()->format('l, F j, Y')">
        <a href="{{ route('pos.index') }}" class="btn-primary"><x-icon name="pos" class="h-4 w-4" /> Open POS</a>
    </x-page-header>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <p class="section-title">Today's Sales</p>
            <p class="num mt-3 text-3xl font-bold text-fg">₱{{ number_format($todaySales, 2) }}</p>
            <p class="mt-1 text-xs text-ink-400">Completed sales today</p>
        </div>
        <div class="card card-pad">
            <p class="section-title">Monthly Revenue</p>
            <p class="num mt-3 text-3xl font-bold text-fg">₱{{ number_format($monthlyRevenue, 2) }}</p>
            <p class="mt-1 text-xs text-ink-400">{{ now()->format('F Y') }} · <span class="font-semibold text-emerald-400">₱{{ number_format($monthlyProfit, 2) }} profit</span></p>
        </div>
        <div class="card card-pad">
            <p class="section-title">Total Orders</p>
            <p class="num mt-3 text-3xl font-bold text-fg">{{ number_format($totalOrders) }}</p>
            <p class="mt-1 text-xs text-ink-400">Completed this month</p>
        </div>
        <a href="#low-stock" @class(['card card-pad block transition', 'border-rose-600/60 bg-rose-600/10 hover:bg-rose-600/15' => $lowStock->count() > 0, 'hover:bg-ink-850' => $lowStock->count() === 0])>
            <p class="section-title {{ $lowStock->count() > 0 ? '!text-rose-300' : '' }}">Low Stock Items</p>
            <p @class(['num mt-3 text-3xl font-bold', 'text-rose-400' => $lowStock->count() > 0, 'text-fg' => $lowStock->count() === 0])>{{ $lowStock->count() }}</p>
            <p class="mt-1 text-xs {{ $lowStock->count() > 0 ? 'text-rose-300/80' : 'text-ink-400' }}">
                {{ $lowStock->count() > 0 ? 'Needs restocking' : 'All products are well stocked' }}
            </p>
        </a>
    </div>

    {{-- Charts --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <p class="section-title">Monthly revenue · last 6 months</p>
            <div class="mt-4 h-64"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="card card-pad">
            <p class="section-title">Best sellers · all time</p>
            <div class="mt-4 h-64"><canvas id="bestChart"></canvas></div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-pad lg:col-span-2">
            <p class="section-title">Daily sales · last 14 days</p>
            <div class="mt-4 h-64"><canvas id="dailyChart"></canvas></div>
        </div>

        <div id="low-stock" class="card scroll-mt-6">
            <div class="flex items-center justify-between px-5 pt-5">
                <p class="section-title">Low stock</p>
                <a href="{{ route('products.index', ['status' => 'low']) }}" class="text-xs font-semibold text-brand-400 hover:text-brand-300">View products</a>
            </div>
            @if($lowStock->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-ink-400">Nothing is running low. 🎉</p>
            @else
                <ul class="mt-3 divide-y divide-ink-700/60 px-5 pb-2">
                    @foreach($lowStock as $p)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-fg">{{ $p->name }}</p>
                                <p class="num text-xs text-ink-400">{{ $p->stock }} left · alert at {{ $p->low_stock_threshold }}</p>
                            </div>
                            <x-status-badge :product="$p" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Recent --}}
    <div class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <p class="section-title">Recent transactions</p>
            <a href="{{ route('sales.index') }}" class="text-xs font-semibold text-brand-400 hover:text-brand-300">All sales</a>
        </div>
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Sale</th><th>Date &amp; time</th><th>Items</th><th class="text-right">Total</th><th>Payment</th></tr></thead>
                <tbody>
                @forelse($recent as $sale)
                    <tr>
                        <td><a href="{{ route('sales.show', $sale) }}" class="num font-semibold text-fg hover:text-brand-300">#{{ $sale->id }}</a></td>
                        <td class="num text-ink-300">{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                        <td class="max-w-xs truncate text-ink-300">{{ $sale->items->map(fn ($i) => $i->product->name.' ×'.$i->qty)->implode(', ') }}</td>
                        <td class="num text-right font-semibold text-fg">₱{{ number_format($sale->total, 2) }}</td>
                        <td class="capitalize text-ink-300">{{ $sale->payment_method }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-ink-400">No sales yet. Make your first sale in the POS.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
        // Wait for the bundled app.js (which defines Chart) to run first.
        document.addEventListener('DOMContentLoaded', () => {
            const red = brandColor(), redSoft = brandColor(.18);
            const money = (ctx) => ' ' + peso(ctx.parsed.y ?? ctx.parsed.x);
            const moneyTick = (v) => '₱' + Number(v).toLocaleString('en-PH');

            const monthly = @json($monthly);
            new Chart(document.getElementById('monthlyChart'), {
                type: 'bar',
                data: { labels: monthly.map(r => r.label), datasets: [{ label: 'Revenue', data: monthly.map(r => r.total), backgroundColor: red, borderRadius: 6, maxBarThickness: 44 }] },
                options: { scales: { y: { beginAtZero: true, ticks: { callback: moneyTick }, grid: { color: inkColor(700) } }, x: { grid: { display: false } } }, plugins: { tooltip: { callbacks: { label: money } } } },
            });

            const daily = @json($daily);
            new Chart(document.getElementById('dailyChart'), {
                type: 'line',
                data: { labels: daily.map(r => r.label), datasets: [{ label: 'Sales', data: daily.map(r => r.total), borderColor: red, backgroundColor: redSoft, fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: red, borderWidth: 2 }] },
                options: { scales: { y: { beginAtZero: true, ticks: { callback: moneyTick }, grid: { color: inkColor(700) } }, x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } } }, plugins: { tooltip: { callbacks: { label: money } } } },
            });

            const best = @json($best);
            new Chart(document.getElementById('bestChart'), {
                type: 'bar',
                data: { labels: best.map(r => r.label), datasets: [{ label: 'Sold', data: best.map(r => r.total), backgroundColor: red, borderRadius: 6, maxBarThickness: 26 }] },
                options: { indexAxis: 'y', scales: { x: { beginAtZero: true, grid: { color: inkColor(700) }, ticks: { precision: 0 } }, y: { grid: { display: false } } }, plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.parsed.x + ' sold' } } } },
            });
        });
    </script>
    @endpush
</x-app-layout>
