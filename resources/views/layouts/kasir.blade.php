<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kasir') — POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white shadow-sm">
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

    <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">✓ {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
