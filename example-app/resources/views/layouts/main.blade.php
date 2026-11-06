<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'STOCKRAGE POS')</title>

    <link rel="stylesheet" href="{{ asset('css/stockrage.css') }}">
</head>

<body data-theme="{{ session('theme', 'light') }}">

    <div class="app-container">

        {{-- Sidebar --}}
        @include('components.sidebar')

        {{-- Main Content --}}
        <main class="main-content">
            <div role="note" style="padding:16px;background:#8B0000;color:white">Development reference — sample data</div>
            @yield('content')
        </main>

    </div>

</body>
</html>