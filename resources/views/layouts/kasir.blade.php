<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kasir') — POS</title>
    @include('layouts.partials.pwa-head', ['app' => 'kasir'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="app-body min-h-screen bg-slate-100 font-sans text-slate-900 antialiased @yield('body_class')"
    data-kasir-notifications
    data-kasir-poll-url="{{ route('kasir.pending.poll') }}"
    data-kasir-poll-interval="{{ config('pos.notifications.poll_interval_seconds', 12) }}"
    data-kasir-index-url="{{ route('kasir.index') }}"
    data-kasir-auto-load="{{ config('pos.notifications.auto_load_new_order', true) ? '1' : '0' }}"
>
    @include('layouts.partials.pwa-install-banner', ['app' => 'kasir'])
    <div id="mobile-overlay" class="mobile-overlay pointer-events-none md:hidden" aria-hidden="true"></div>

    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col md:min-h-screen">
            <aside id="mobile-sidebar"
                   class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,85vw)] -translate-x-full flex-col bg-slate-900 text-white transition-transform duration-300 ease-out md:z-30 md:w-64 md:translate-x-0">
                <div class="border-b border-slate-800 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold">K</div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ config('pos.shop_name', 'Point of Sale') }}</p>
                            <p class="truncate text-xs text-slate-400">Modul Kasir</p>
                        </div>
                    </div>
                </div>

                <nav class="flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-4">
                    <a href="{{ route('kasir.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.index') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🛒</span>
                        Point of Sale
                    </a>
                    <a href="{{ route('kasir.orders') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.orders*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">📋</span>
                        Riwayat Pesanan
                    </a>
                    <a href="{{ route('kasir.tables') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.tables', 'kasir.barcode') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🪑</span>
                        Meja QR
                    </a>
                    <a href="{{ route('kasir.products.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.products*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🍽️</span>
                        Kelola Menu
                    </a>
                    <a href="{{ route('kasir.menu-categories.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.menu-categories*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🏷️</span>
                        Atur Kategori
                    </a>
                    <a href="{{ route('kasir.pembukuan.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('kasir.pembukuan*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">📒</span>
                        Pembukuan
                    </a>
                </nav>

                <div class="border-t border-slate-800 px-4 py-4">
                    <div class="mb-3 rounded-lg bg-slate-800/80 px-3 py-2">
                        <p class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ auth()->user()->role->label() }}</p>
                    </div>
                    <div class="kasir-sidebar-foot-actions">
                        <button
                            type="button"
                            class="kasir-sound-toggle"
                            data-kasir-sound-toggle
                            aria-pressed="true"
                            title="Suara notifikasi"
                        >
                            <span class="kasir-sound-toggle-icon" aria-hidden="true">🔔</span>
                            <span class="kasir-sound-toggle-label" data-kasir-sound-label>Suara aktif</span>
                        </button>
                        <form action="{{ route('logout') }}" method="POST" class="kasir-sidebar-foot-form">
                            @csrf
                            <button type="submit" class="kasir-sidebar-logout">
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
                        <p class="truncate text-sm font-semibold text-slate-900">@yield('heading', 'Kasir POS')</p>
                        <p class="truncate text-[11px] text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                    @hasSection('mobile_topbar_actions')
                        <div class="mobile-topbar-actions">
                            @yield('mobile_topbar_actions')
                        </div>
                    @endif
                </div>

                <header class="kasir-page-header sticky top-0 z-20 hidden shrink-0 border-b border-slate-200 bg-white/90 backdrop-blur md:block">
                    <div class="flex items-center justify-between px-4 py-4 sm:px-6">
                        <div>
                            <h1 class="text-xl font-semibold text-slate-900">@yield('heading', 'Kasir POS')</h1>
                            @hasSection('subheading')
                                <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500">{{ auth()->user()->name }}</p>
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
