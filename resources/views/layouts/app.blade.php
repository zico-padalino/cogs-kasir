<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panduan') — COGS Sederhana</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-slate-900 text-white md:flex">
            <div class="border-b border-slate-800 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 font-bold">C</div>
                    <div>
                        <p class="text-sm font-semibold">COGS Sederhana</p>
                        <p class="text-xs text-slate-400">Hitung biaya produk</p>
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

            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
                @if ($setupFullyComplete ?? false)
                    <a href="{{ route('dashboard') }}"
                       class="mb-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">🏠</span>
                        Beranda
                    </a>

                    @foreach ($setupSteps ?? [] as $step)
                        @php $active = request()->routeIs($step['route'].'*') || request()->routeIs($step['route']); @endphp
                        <a href="{{ route($step['route']) }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span class="truncate">{{ $step['short'] }}</span>
                        </a>
                    @endforeach
                @else
                    <a href="{{ route('dashboard') }}"
                       class="mb-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded bg-white/10 text-xs">📋</span>
                        Panduan
                    </a>

                    @foreach ($setupSteps ?? [] as $step)
                        @php $active = request()->routeIs($step['route'].'*') || request()->routeIs($step['route']); @endphp
                        <a href="{{ route($step['route']) }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step['done'] ? 'bg-green-500 text-white' : ($active ? 'bg-white/20' : 'bg-slate-700') }}">
                                {{ $step['done'] ? '✓' : $step['number'] }}
                            </span>
                            <span class="truncate">{{ $step['short'] }}</span>
                        </a>
                    @endforeach
                @endif
            </nav>

            <div class="border-t border-slate-800 px-4 py-4">
                @auth
                    <div class="mb-3 rounded-lg bg-slate-800/80 px-3 py-2">
                        <p class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ auth()->user()->role->label() }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-300 transition hover:bg-slate-800 hover:text-white">
                            <span>↩</span> Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="mb-3 flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-brand-300 transition hover:bg-slate-800 hover:text-white">
                        <span>🔐</span> Masuk
                    </a>
                @endauth

                <a href="{{ route('reset-data.show') }}"
                   class="mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-red-400 transition hover:bg-red-950 hover:text-red-300">
                    <span>🗑️</span> Reset Data
                </a>
                <p class="mt-2 px-3 text-xs text-slate-500">Database · MySQL</p>
            </div>
        </aside>

        <div class="flex flex-1 flex-col md:pl-64">
            @if (! ($setupFullyComplete ?? false))
                <div class="border-b border-slate-200 bg-white px-4 py-2 md:hidden">
                    <div class="flex gap-2 overflow-x-auto text-xs">
                        <a href="{{ route('dashboard') }}" class="shrink-0 rounded-full bg-brand-100 px-3 py-1 text-brand-700">Panduan</a>
                        @foreach (collect($setupSteps ?? [])->take(4) as $step)
                            <a href="{{ route($step['route']) }}" class="shrink-0 rounded-full bg-slate-100 px-3 py-1">{{ $step['short'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex items-center justify-between px-4 py-4 sm:px-8">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">@yield('heading')</h1>
                        @hasSection('subheading')
                            <p class="mt-0.5 text-sm text-slate-500">@yield('subheading')</p>
                        @endif
                    </div>
                    @if (! ($setupFullyComplete ?? false))
                        <a href="{{ route('dashboard') }}" class="hidden text-sm text-brand-600 hover:text-brand-700 sm:block">← Panduan</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="hidden text-sm text-brand-600 hover:text-brand-700 sm:block">← Beranda</a>
                    @endif
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-8">
                @if (session('success'))
                    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        ✓ {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
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
</body>
</html>
