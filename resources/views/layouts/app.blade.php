<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', 'Panduan') — {{ config('pos.shop_name', 'Hitung Modal Menu') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div id="mobile-overlay" class="mobile-overlay pointer-events-none md:hidden" aria-hidden="true"></div>

    <div class="app-shell">
        <div class="app-frame flex min-h-0 flex-1 flex-col md:min-h-screen">
        <aside id="mobile-sidebar"
               class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,85vw)] -translate-x-full flex-col bg-slate-900 text-white transition-transform duration-300 ease-out md:z-30 md:w-64 md:translate-x-0">
            <div class="border-b border-slate-800 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-600 font-bold">C</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">Hitung Modal Menu</p>
                        <p class="truncate text-xs text-slate-400">Dari bahan sampai harga jual</p>
                    </div>
                </div>
            </div>

            @if (! ($setupFullyComplete ?? false))
                <div class="border-b border-slate-800 px-4 py-3">
                    <div class="flex justify-between text-xs text-slate-400">
                        <span>Progress</span>
                        <span>{{ $setupPercent ?? 0 }}%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-700">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ $setupPercent ?? 0 }}%"></div>
                    </div>
                </div>
            @endif

            <nav class="flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-4">
                @if ($setupFullyComplete ?? false)
                    <a href="{{ route('dashboard') }}"
                       class="mb-2 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🏠</span>
                        Beranda
                    </a>

                    @foreach ($setupSteps ?? [] as $step)
                        @php $active = request()->routeIs($step['route'].'*') || request()->routeIs($step['route']); @endphp
                        <a href="{{ route($step['route']) }}"
                           class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span class="truncate">{{ $step['short'] }}</span>
                        </a>
                    @endforeach
                @else
                    <a href="{{ route('dashboard') }}"
                       class="mb-2 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">📋</span>
                        Panduan
                    </a>

                    @foreach ($setupSteps ?? [] as $step)
                        @php $active = request()->routeIs($step['route'].'*') || request()->routeIs($step['route']); @endphp
                        <a href="{{ route($step['route']) }}"
                           class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step['done'] ? 'bg-green-500 text-white' : ($active ? 'bg-white/20' : 'bg-slate-700') }}">
                                {{ $step['done'] ? '✓' : $step['number'] }}
                            </span>
                            <span class="truncate">{{ $step['short'] }}</span>
                        </a>
                    @endforeach
                @endif

                @auth
                    @if (count(auth()->user()->accessibleModules()) > 1)
                        <a href="{{ route('hub') }}"
                           class="mt-2 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition text-slate-300 hover:bg-slate-800">
                            <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">↔</span>
                            Ganti Modul
                        </a>
                    @endif
                @endauth
            </nav>

            <div class="border-t border-slate-800 px-4 py-4">
                @auth
                    <div class="mb-3 rounded-lg bg-slate-800/80 px-3 py-2">
                        <p class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ auth()->user()->role->label() }}</p>
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
                @else
                    <a href="{{ route('login') }}"
                       class="mb-3 flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-xs text-brand-300 transition hover:bg-slate-800 hover:text-white">
                        <span>🔐</span> Masuk
                    </a>
                @endauth

                <a href="{{ route('reset-data.show') }}"
                   class="mt-2 flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-xs text-red-400 transition hover:bg-red-950 hover:text-red-300">
                    <span>🗑️</span> Hapus Semua Data
                </a>
            </div>
        </aside>

        <div class="app-content flex min-h-0 min-w-0 flex-1 flex-col md:pl-64">
            <div class="mobile-topbar md:hidden">
                <button type="button" class="mobile-menu-btn" data-mobile-menu-toggle aria-label="Buka menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">@yield('heading')</p>
                </div>
            </div>

            <header class="sticky top-0 z-20 hidden border-b border-slate-200 bg-white/90 backdrop-blur md:block">
                <div class="flex items-center justify-between px-4 py-4 sm:px-8">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">@yield('heading')</h1>
                        @hasSection('subheading')
                            <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
                        @endif
                    </div>
                    @if (! ($setupFullyComplete ?? false))
                        <a href="{{ route('dashboard') }}" class="text-sm text-brand-600 hover:text-brand-700">← Panduan</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="text-sm text-brand-600 hover:text-brand-700">← Beranda</a>
                    @endif
                </div>
            </header>

            <main class="app-scroll min-h-0 flex-1 px-4 py-4 sm:px-8 sm:py-6">
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

                @if (!request()->routeIs('dashboard'))
                    <x-step-progress />
                @endif

                @yield('content')
            </main>
        </div>
        </div>
    </div>

    @stack('modals')
    @stack('scripts')
</body>
</html>
