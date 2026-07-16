<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
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
<body class="app-body min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div id="mobile-overlay" class="mobile-overlay pointer-events-none md:hidden" aria-hidden="true"></div>

    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col md:min-h-screen">
            <aside id="mobile-sidebar"
                   class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,85vw)] -translate-x-full flex-col bg-slate-900 text-white transition-transform duration-300 ease-out md:z-30 md:w-64 md:translate-x-0">
                <div class="border-b border-slate-800 px-5 py-4">
                    <div class="flex items-start gap-3">
                        @include('layouts.partials.shop-brand-mark')
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ config('pos.shop_name', 'Point of Sale') }}</p>
                            <p class="truncate text-xs text-slate-400">Modul Admin</p>
                        </div>
                        @include('layouts.partials.sidebar-collapse-btn')
                    </div>
                </div>

                <nav class="flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-4">
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🏠</span>
                        Dashboard
                    </a>
                    <a href="{{ route('admin.employees.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.employees.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">👤</span>
                        Data Karyawan
                    </a>
                    <a href="{{ route('admin.attendances.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.attendances.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">📅</span>
                        Absensi
                    </a>
                    <a href="{{ route('admin.salaries.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.salaries.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">💰</span>
                        Gaji Karyawan
                    </a>
                    <a href="{{ route('admin.users.index') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.users.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🔐</span>
                        Akses Akun
                    </a>
                    <a href="{{ route('admin.settings.edit') }}"
                       class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">⚙</span>
                        Pengaturan
                    </a>
                    @if (auth()->user()->accessibleModules() !== [] && count(auth()->user()->accessibleModules()) > 1)
                        <a href="{{ route('hub') }}"
                           class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition text-slate-300 hover:bg-slate-800">
                            <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">↔</span>
                            Ganti Modul
                        </a>
                    @endif
                </nav>

                <div class="border-t border-slate-800 px-4 py-4">
                    <div class="mb-3 rounded-lg bg-slate-800/80 px-3 py-2">
                        <p class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-slate-400">Admin</p>
                    </div>
                    <a href="{{ route('password.edit') }}"
                       class="mb-2 flex min-h-10 w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-300 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('password.*') ? 'bg-slate-800 text-white' : '' }}">
                        <span>🔑</span> Ubah Password
                    </a>
                    @if (auth()->user()->hasModule(\App\Enums\UserRole::Kasir))
                        <a href="{{ route('pin.edit') }}"
                           class="mb-2 flex min-h-10 w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-300 transition hover:bg-slate-800 hover:text-white {{ request()->routeIs('pin.*') ? 'bg-slate-800 text-white' : '' }}">
                            <span>🔢</span> Atur PIN Kasir
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-300 transition hover:bg-slate-800 hover:text-white">
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
