<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kasir') — POS</title>
    @include('layouts.partials.pwa-head', ['app' => 'kasir'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            function syncVvh() {
                var vv = window.visualViewport;
                var h = vv ? Math.round(vv.height) : window.innerHeight;
                document.documentElement.style.setProperty('--vvh', Math.max(240, h) + 'px');
            }
            syncVvh();
            window.addEventListener('resize', syncVvh);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncVvh);
            }
        })();
    </script>
</head>
<body
    class="app-body min-h-screen bg-[#f6f1ea] font-sans text-slate-900 antialiased @yield('body_class')"
    data-kasir-notifications
    data-kasir-poll-url="{{ route('kasir.pending.poll') }}"
    data-kasir-poll-interval="{{ config('pos.notifications.poll_interval_seconds', 12) }}"
    data-kasir-index-url="{{ route('kasir.index') }}"
    data-kasir-auto-load="{{ config('pos.notifications.auto_load_new_order', true) ? '1' : '0' }}"
    data-kasir-pin-unlock-url="{{ route('kasir.pin.unlock') }}"
    data-kasir-pin-status-url="{{ route('kasir.pin.status') }}"
    data-kasir-pin-touch-url="{{ route('kasir.pin.touch') }}"
    data-kasir-pin-expires-at="{{ \App\Support\KasirPin::expiresAtTimestamp() ?? '' }}"
    data-kasir-server-now="{{ now()->getTimestamp() }}"
    data-kasir-pin-ttl-minutes="{{ \App\Support\KasirPin::idleMinutes() }}"
    data-kasir-push-vapid-url="{{ route('kasir.push.vapid') }}"
    data-kasir-push-subscribe-url="{{ route('kasir.push.subscribe') }}"
    data-kasir-push-unsubscribe-url="{{ route('kasir.push.unsubscribe') }}"
>
    @include('layouts.partials.pwa-install-banner', ['app' => 'kasir'])
    <div id="mobile-overlay" class="mobile-overlay pointer-events-none md:hidden" aria-hidden="true"></div>

    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col md:min-h-screen">
            <aside id="mobile-sidebar" class="app-sidebar -translate-x-full md:translate-x-0">
                <div class="app-sidebar-brand">
                    <div class="app-sidebar-brand-row">
                        @include('layouts.partials.shop-brand-mark')
                        <div class="app-sidebar-brand-copy">
                            <p class="app-sidebar-shop-name">{{ config('pos.shop_name', 'Point of Sale') }}</p>
                            <p class="app-sidebar-shop-meta">Modul Kasir</p>
                        </div>
                        @include('layouts.partials.sidebar-collapse-btn')
                    </div>
                </div>

                <nav class="app-sidebar-nav">
                    <a href="{{ route('kasir.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.index') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🛒</span>
                        Point of Sale
                    </a>
                    <a href="{{ route('kasir.orders') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.orders*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">📋</span>
                        Riwayat Pesanan
                    </a>
                    <a href="{{ route('kasir.tables') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.tables', 'kasir.barcode') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🪑</span>
                        Meja QR
                    </a>
                    <a href="{{ route('kasir.products.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.products*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🍽️</span>
                        Kelola Menu
                    </a>
                    <a href="{{ route('kasir.menu-categories.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.menu-categories*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🏷️</span>
                        Atur Kategori
                    </a>
                    <a href="{{ route('kasir.pembukuan.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.pembukuan*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">📒</span>
                        Pembukuan
                    </a>
                    <a href="{{ route('kasir.kas-tunai.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('kasir.kas-tunai*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">💵</span>
                        Kas Tunai
                    </a>
                    @if (auth()->user()->accessibleModules() !== [] && count(auth()->user()->accessibleModules()) > 1)
                        <a href="{{ route('hub') }}" class="app-sidebar-link">
                            <span class="app-sidebar-link-icon">↔</span>
                            Ganti Modul
                        </a>
                    @endif
                </nav>

                <div class="app-sidebar-foot">
                    <div class="app-sidebar-user">
                        @php
                            $kasirOperator = \App\Support\KasirPin::operatorEmployee();
                            $stationUser = auth()->user();
                        @endphp
                        <p class="app-sidebar-user-name">{{ $kasirOperator?->name ?? $stationUser->name }}</p>
                        <p class="app-sidebar-user-meta">
                            @if ($kasirOperator)
                                Kasir bertugas · PIN aktif
                            @else
                                {{ $stationUser->role->label() }}
                            @endif
                        </p>
                        @if ($kasirOperator)
                            <p class="app-sidebar-user-hint">Stasiun: {{ $stationUser->name }}</p>
                        @endif
                    </div>
                    <div class="space-y-1">
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.employees.index') }}" class="kasir-sidebar-logout">
                                <span aria-hidden="true">👥</span>
                                <span>Data Karyawan / PIN</span>
                            </a>
                        @endif
                        <a href="{{ route('password.edit') }}" class="kasir-sidebar-logout {{ request()->routeIs('password.*') ? 'bg-brand-100 text-espresso' : '' }}">
                            <span aria-hidden="true">🔑</span>
                            <span>Ubah Password</span>
                        </a>
                        <form action="{{ route('kasir.pin.lock') }}" method="POST">
                            @csrf
                            <button type="submit" class="kasir-sidebar-logout w-full">
                                <span aria-hidden="true">🔒</span>
                                <span>Kunci Kasir</span>
                            </button>
                        </form>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="kasir-sidebar-logout w-full">
                                <span aria-hidden="true">↩</span>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="app-content flex min-h-0 min-w-0 flex-1 flex-col md:pl-64">
                <div class="mobile-topbar shrink-0 md:hidden">
                    <button type="button" class="mobile-menu-btn" data-mobile-menu-toggle aria-label="Buka menu">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="mobile-topbar-title truncate text-sm font-semibold">@yield('heading', 'Kasir POS')</p>
                        <p class="mobile-topbar-subtitle truncate text-[11px]">{{ \App\Support\KasirPin::operatorName() }}</p>
                    </div>
                    @hasSection('mobile_topbar_actions')
                        <div class="mobile-topbar-actions">
                            @yield('mobile_topbar_actions')
                        </div>
                    @endif
                </div>

                <header class="kasir-page-header sticky top-0 z-20 hidden shrink-0 border-b border-slate-200 bg-white/90 backdrop-blur md:block">
                    <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" class="sidebar-header-toggle" data-sidebar-expand aria-label="Tampilkan menu" title="Tampilkan menu" hidden>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold text-slate-900">@yield('heading', 'Kasir POS')</h1>
                                @hasSection('subheading')
                                    <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
                                @endif
                            </div>
                        </div>
                        <p class="shrink-0 text-sm text-slate-500">{{ \App\Support\KasirPin::operatorName() }}</p>
                    </div>
                </header>

                <main class="app-scroll min-h-0 flex-1 @yield('main_class', 'px-4 py-4 sm:px-6 sm:py-6')">
                    @php $usePosFlash = request()->routeIs('kasir.index'); @endphp
                    @if (session('success'))
                        <div @class([
                            'pos-flash pos-flash-success' => $usePosFlash,
                            'mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800' => ! $usePosFlash,
                        ]) @if($usePosFlash) data-pos-flash data-pos-flash-success @endif>✓ {{ session('success') }}</div>
                    @endif
                    @if (session('warning'))
                        <div @class([
                            'pos-flash pos-flash-warning' => $usePosFlash,
                            'mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900' => ! $usePosFlash,
                        ]) @if($usePosFlash) data-pos-flash @endif role="status">⚠ {{ session('warning') }}</div>
                    @endif
                    @if (session('error'))
                        <div @class([
                            'pos-flash pos-flash-error' => $usePosFlash,
                            'mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800' => ! $usePosFlash,
                        ]) @if($usePosFlash) data-pos-flash @endif>{{ session('error') }}</div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    </div>
</body>
</html>
