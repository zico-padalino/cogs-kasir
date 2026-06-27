<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', 'Kasir') — POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body min-h-screen bg-slate-100 font-sans text-slate-900 antialiased @yield('body_class')">
    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col">
            {{-- Mobile top bar --}}
            <header class="mobile-topbar shrink-0 md:hidden">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">K</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">@yield('heading', 'Kasir POS')</p>
                        <p class="truncate text-[11px] text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                </div>
            </header>

            {{-- Desktop header --}}
            <header class="sticky top-0 z-30 hidden border-b border-slate-200 bg-white shadow-sm md:block">
                <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">K</div>
                        <div>
                            <p class="font-bold text-slate-900">Kasir POS</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->name }}</p>
                        </div>
                    </div>
                    <nav class="flex flex-wrap items-center gap-2 text-sm">
                        <a href="{{ route('kasir.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('kasir.index') ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Kasir</a>
                        <a href="{{ route('kasir.orders') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('kasir.orders*') ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Riwayat</a>
                        <a href="{{ route('kasir.tables') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('kasir.tables') ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Meja & Barcode</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-outline btn-sm">Keluar</button>
                        </form>
                    </nav>
                </div>
            </header>

            <main class="app-content flex min-h-0 flex-1 flex-col">
                <div class="app-scroll mx-auto w-full max-w-[1600px] flex-1 @yield('main_class', 'px-4 py-4 sm:px-6 sm:py-6')">
                    @if (session('success'))
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">✓ {{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                    @endif
                    @yield('content')
                    <div id="bottom-nav-spacer" class="bottom-nav-spacer md:hidden" aria-hidden="true"></div>
                </div>
            </main>
        </div>
    </div>

    <nav id="bottom-nav" class="bottom-nav md:hidden" aria-label="Navigasi kasir">
        <div class="bottom-nav-inner kasir-bottom-nav">
            <a href="{{ route('kasir.index') }}"
               class="bottom-nav-link {{ request()->routeIs('kasir.index') ? 'is-active' : '' }}">
                <span class="bottom-nav-icon">🛒</span>
                <span>Kasir</span>
            </a>
            <a href="{{ route('kasir.orders') }}"
               class="bottom-nav-link {{ request()->routeIs('kasir.orders*') ? 'is-active' : '' }}">
                <span class="bottom-nav-icon">📋</span>
                <span>Riwayat</span>
            </a>
            <a href="{{ route('kasir.tables') }}"
               class="bottom-nav-link {{ request()->routeIs('kasir.tables') ? 'is-active' : '' }}">
                <span class="bottom-nav-icon">🪑</span>
                <span>Meja</span>
            </a>
            <form action="{{ route('logout') }}" method="POST" class="contents">
                @csrf
                <button type="submit" class="bottom-nav-link w-full">
                    <span class="bottom-nav-icon">↩</span>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </nav>
</body>
</html>
