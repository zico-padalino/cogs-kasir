<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <meta name="robots" content="index, follow">
    <title>@yield('title') — {{ $shopName ?? config('pos.shop_name', 'Kedai Tjoan') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f1ea] font-sans text-slate-900 antialiased">
    <div class="legal-shell">
        <header class="legal-header">
            @include('layouts.partials.shop-brand-mark', ['sizeClass' => 'h-10 w-10', 'roundedClass' => 'rounded-2xl', 'textClass' => 'text-base'])
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-700/70">{{ $shopName }}</p>
                <h1 class="truncate text-lg font-bold text-slate-900">@yield('heading')</h1>
            </div>
            <a href="{{ route('order.menu') }}" class="legal-back-link">← Menu</a>
        </header>

        <main class="legal-main">
            @yield('content')
        </main>

        <footer class="legal-footer">
            <a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('order.menu') }}">Pesan Online</a>
            <p class="mt-2 text-[11px] text-slate-400">Harga dalam Rupiah (IDR) · {{ $shopName }}</p>
        </footer>
    </div>
</body>
</html>
