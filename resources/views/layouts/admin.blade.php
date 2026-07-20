<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('pos.shop_name', 'POS') }}</title>
    @include('layouts.partials.favicon')
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
<body class="app-body min-h-screen bg-[#f6f1ea] font-sans text-slate-900 antialiased">
    <div id="mobile-overlay" class="mobile-overlay pointer-events-none md:hidden" aria-hidden="true"></div>

    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col md:min-h-screen">
            <aside id="mobile-sidebar" class="app-sidebar -translate-x-full md:translate-x-0">
                <div class="app-sidebar-brand">
                    <div class="app-sidebar-brand-row">
                        @include('layouts.partials.shop-brand-mark')
                        <div class="app-sidebar-brand-copy">
                            <p class="app-sidebar-shop-name">{{ config('pos.shop_name', 'Point of Sale') }}</p>
                            <p class="app-sidebar-shop-meta">Modul Admin</p>
                        </div>
                        @include('layouts.partials.sidebar-collapse-btn')
                    </div>
                </div>

                <nav class="app-sidebar-nav">
                    <a href="{{ route('admin.dashboard') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🏠</span>
                        Dashboard
                    </a>
                    <a href="{{ route('admin.employees.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.employees.*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">👤</span>
                        Data Karyawan
                    </a>
                    <a href="{{ route('admin.attendances.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.attendances.index') || request()->routeIs('admin.attendances.store') || request()->routeIs('admin.attendances.destroy') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">📅</span>
                        Absensi
                    </a>
                    <a href="{{ route('admin.attendances.qr') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.attendances.qr') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">▦</span>
                        QR Absensi
                    </a>
                    <a href="{{ route('admin.salaries.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.salaries.*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">💰</span>
                        Gaji Karyawan
                    </a>
                    <a href="{{ route('admin.users.index') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">🔐</span>
                        Akses Akun
                    </a>
                    <a href="{{ route('admin.settings.edit') }}"
                       class="app-sidebar-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                        <span class="app-sidebar-link-icon">⚙</span>
                        Pengaturan
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
                        <p class="app-sidebar-user-name">{{ auth()->user()->name }}</p>
                        <p class="app-sidebar-user-meta">Admin</p>
                    </div>
                    <a href="{{ route('password.edit') }}"
                       class="app-sidebar-action {{ request()->routeIs('password.*') ? 'is-active' : '' }}">
                        <span>🔑</span> Ubah Password
                    </a>
                    @if (auth()->user()->hasModule(\App\Enums\UserRole::Kasir))
                        <a href="{{ route('pin.edit') }}"
                           class="app-sidebar-action {{ request()->routeIs('pin.*') ? 'is-active' : '' }}">
                            <span>🔢</span> Atur PIN Kasir
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="app-sidebar-action">
                            <span>↩</span> Keluar
                        </button>
                    </form>
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
                        <p class="truncate text-sm font-semibold text-slate-900">@yield('heading', 'Admin')</p>
                        <p class="truncate text-[11px] text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                </div>

                <header class="sticky top-0 z-20 hidden shrink-0 border-b border-slate-200 bg-white/90 backdrop-blur md:block">
                    <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-6">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" class="sidebar-header-toggle" data-sidebar-expand aria-label="Tampilkan menu" title="Tampilkan menu" hidden>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold text-slate-900">@yield('heading', 'Admin')</h1>
                                @hasSection('subheading')
                                    <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
                                @endif
                            </div>
                        </div>
                        <p class="shrink-0 text-sm text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                </header>

                <main class="app-scroll min-h-0 flex-1 px-4 py-4 sm:px-6 sm:py-6">
                    @if (session('success'))
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 sm:mb-6">
                            ✓ {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:mb-6">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:mb-6">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    </div>
</body>
</html>
