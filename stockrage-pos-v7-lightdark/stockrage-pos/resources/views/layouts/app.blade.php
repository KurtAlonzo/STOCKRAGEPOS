@php
    $business = \App\Models\Setting::get('business_name', 'Takoyaki POS');
    $lowCount = \App\Models\Product::active()->whereColumn('stock', '<=', 'low_stock_threshold')->count();

    // Sidebar links: [route, label, icon, active pattern, hint]
    $links = [
        ['dashboard', 'Dashboard', 'dashboard', 'dashboard', null],
        ['pos.index', 'POS', 'pos', 'pos*', null],
        ['products.index', 'Products', 'products', 'products*', $lowCount ? $lowCount.' running low' : null],
        ['sales.index', 'Sales history', 'sales', 'sales*', null],
        ['stock-history.index', 'Stock history', 'stock', 'stock-history*', null],
        ['reports.index', 'Reports', 'reports', 'reports*', null],
        ['settings.edit', 'Settings', 'settings', 'settings*', null],
    ];

    // Small "today so far" summary for the bottom of the sidebar.
    $todayDate = now()->toDateString();
    $todayTotal = (float) \App\Models\Sale::completed()->whereDate('created_at', $todayDate)->sum('total');
    $todayCount = \App\Models\Sale::completed()->whereDate('created_at', $todayDate)->count();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="{{ $theme = \App\Support\Theme::current() }}">
<head>
    <meta name="theme-color" content="{{ \App\Support\Theme::swatch($theme) }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title') ? $attributes->get('title').' · ' : '' }}Stockrage POS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @include('partials.mode-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans">
{{-- `collapsed` = desktop icon-rail mode (remembered in this browser). `mobile` = slide-in drawer on phones. --}}
<div x-data="{
        mobile: false,
        collapsed: (() => { try { return localStorage.getItem('stockrage.sidebar') === 'collapsed'; } catch (e) { return false; } })(),
        toggle() {
            this.collapsed = !this.collapsed;
            try { localStorage.setItem('stockrage.sidebar', this.collapsed ? 'collapsed' : 'open'); } catch (e) {}
        },
     }"
     @keydown.escape.window="mobile = false"
     class="min-h-screen lg:flex">

    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex items-center justify-between border-b border-ink-700 bg-ink-900/95 px-4 py-3 backdrop-blur lg:hidden print:hidden">
        <button type="button" @click="mobile = true" class="rounded-lg p-2 text-ink-200 hover:bg-ink-800" aria-label="Open menu">
            <x-icon name="menu" class="h-6 w-6" />
        </button>
        <img src="{{ asset('images/stockrage-icon.png') }}" alt="Stockrage POS" class="h-8 w-8">
        <x-mode-toggle :label="false" class="p-2" />
    </div>

    {{-- Backdrop (mobile) --}}
    <div x-cloak x-show="mobile" x-transition.opacity @click="mobile = false" class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-ink-700 bg-ink-900 transition-all duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 print:hidden"
        :class="[mobile ? 'translate-x-0' : '', collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64']"
        aria-label="Sidebar">

        {{-- Logo and shop name --}}
        <div class="px-5 pb-3 pt-5" :class="collapsed ? 'lg:px-0' : ''">
            <div class="flex items-center justify-between gap-2" :class="collapsed ? 'lg:justify-center' : ''">
                <a href="{{ route('dashboard') }}" class="block min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                    <img src="{{ asset('images/stockrage-logo-light.png') }}" alt="Stockrage POS" class="hidden h-9 w-auto dark:block">
                    <img src="{{ asset('images/stockrage-logo.png') }}" alt="Stockrage POS" class="h-9 w-auto dark:hidden">
                </a>
                <a href="{{ route('dashboard') }}" class="hidden" :class="collapsed ? 'lg:block' : ''" aria-label="Stockrage POS">
                    <img src="{{ asset('images/stockrage-icon.png') }}" alt="" class="h-9 w-9">
                </a>
                <button type="button" @click="mobile = false" class="rounded-lg p-2 text-ink-300 hover:bg-ink-800 lg:hidden" aria-label="Close menu">
                    <x-icon name="close" />
                </button>
            </div>
            <p class="mt-2 truncate text-sm text-ink-400" :class="collapsed ? 'lg:hidden' : ''">{{ $business }}</p>
        </div>

        <div class="px-3 pb-2" :class="collapsed ? 'lg:px-2' : ''">
            <x-mode-toggle class="h-8 w-full px-3" x-bind:class="collapsed ? 'lg:justify-center lg:px-0' : ''" />
        </div>

        {{-- Hide / show the menu (desktop) --}}
        <div class="hidden px-3 pb-2 lg:block" :class="collapsed ? 'lg:px-2' : ''">
            <button type="button" @click="toggle()"
                    class="flex h-8 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-ink-400 transition hover:bg-ink-850 hover:text-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    :class="collapsed ? 'lg:justify-center lg:px-0' : ''"
                    :aria-label="collapsed ? 'Show menu' : 'Hide menu'" :title="collapsed ? 'Show menu' : 'Hide menu'">
                <svg class="h-5 w-5 shrink-0 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                <span :class="collapsed ? 'lg:hidden' : ''">Hide menu</span>
            </button>
        </div>

        {{-- Links --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2" :class="collapsed ? 'lg:overflow-visible lg:px-2' : ''" aria-label="Main">
            @foreach($links as [$route, $label, $icon, $pattern, $hint])
                <x-sidebar-link :href="route($route)" :icon="$icon" :active="request()->routeIs($pattern)" :hint="$hint">{{ $label }}</x-sidebar-link>
            @endforeach
        </nav>

        {{-- Footer: today so far, who is logged in --}}
        <div class="border-t border-ink-700 p-3" :class="collapsed ? 'lg:px-2' : ''">
            <a href="{{ route('reports.index') }}" class="mb-3 block rounded-lg border border-ink-700 bg-ink-850 px-3 py-2.5 transition hover:border-ink-600" :class="collapsed ? 'lg:hidden' : ''">
                <span class="block text-xs text-ink-400">Today so far</span>
                <span class="num block text-lg font-bold leading-tight text-fg">₱{{ number_format($todayTotal, 2) }}</span>
                <span class="block text-xs text-ink-400">{{ $todayCount }} {{ \Illuminate\Support\Str::plural('sale', $todayCount) }}</span>
            </a>

            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-850" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="Your profile">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink-700 text-sm font-bold text-fg">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="min-w-0 leading-tight" :class="collapsed ? 'lg:hidden' : ''">
                    <span class="block text-xs text-ink-400">{{ $greeting }}</span>
                    <span class="block truncate text-sm font-semibold text-fg">{{ auth()->user()->name }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-ink-400 transition hover:bg-ink-850 hover:text-fg" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="Log out">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center"><x-icon name="logout" class="h-5 w-5" /></span>
                    <span :class="collapsed ? 'lg:hidden' : ''">Log out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="min-w-0 flex-1">
        <div @class(['mx-auto w-full px-4 py-6 sm:px-6 lg:px-8 lg:py-8', 'max-w-[1500px]' => $attributes->has('wide'), 'max-w-7xl' => ! $attributes->has('wide')])>

            {{-- Flash messages --}}
            @foreach (['success' => 'emerald', 'error' => 'rose'] as $type => $color)
                @if (session($type))
                    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, {{ $type === 'error' ? 9000 : 5000 }})"
                         x-show="show" x-transition role="status"
                         class="mb-5 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm font-medium print:hidden
                                {{ $type === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300' }}">
                        <span>{{ session($type) }}</span>
                        <button type="button" @click="show = false" class="opacity-70 hover:opacity-100" aria-label="Dismiss"><x-icon name="close" class="h-4 w-4" /></button>
                    </div>
                @endif
            @endforeach

            {{ $slot }}
        </div>
    </main>
</div>

@stack('scripts')
</body>
</html>
