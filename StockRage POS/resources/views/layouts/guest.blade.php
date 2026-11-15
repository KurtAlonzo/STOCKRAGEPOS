<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" data-theme="{{ $theme = \App\Support\Theme::current() }}">
<head>
    <meta name="theme-color" content="{{ \App\Support\Theme::swatch($theme) }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log in · Stockrage POS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @include('partials.mode-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans">
<div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10">
    {{-- soft brand glow --}}
    <div class="pointer-events-none absolute -top-40 left-1/2 hidden dark:block h-[28rem] w-[44rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>

    <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-ink-700 bg-ink-900 shadow-xl shadow-black/10 dark:shadow-2xl dark:shadow-black/50">
        <div class="px-8 pb-2 pt-8">
            <img src="{{ asset('images/stockrage-logo-light.png') }}" alt="Stockrage POS" class="mx-auto hidden h-14 w-auto dark:block">
            <img src="{{ asset('images/stockrage-logo.png') }}" alt="Stockrage POS" class="mx-auto h-14 w-auto dark:hidden">
        </div>
        <div class="px-8 py-8">
            {{ $slot }}
        </div>
    </div>
    <x-mode-toggle class="absolute right-4 top-4 px-3 py-2" />
    <p class="relative mt-6 text-xs text-ink-500">Inventory &amp; point of sale</p>
</div>
</body>
</html>
