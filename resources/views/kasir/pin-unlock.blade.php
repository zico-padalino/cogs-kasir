<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PIN Kasir — {{ $shopName }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <div class="login-glow" aria-hidden="true"></div>

    <div class="login-card max-w-sm">
        <div class="login-brand">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="login-logo-img">
            @else
                <div class="login-logo-fallback">{{ \App\Support\ShopSettings::initial() }}</div>
            @endif
            <div class="login-brand-copy">
                <h1 class="login-shop-name">{{ $shopName }}</h1>
                <p class="login-shop-title">PIN menentukan siapa yang bertugas</p>
            </div>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="auth-alert-error mb-4" role="alert">{{ $errors->first() }}</div>
        @endif

        <p class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600">
            Login stasiun boleh tetap sama. Masukkan <strong>PIN milik kasir yang sedang bertugas</strong>
            — transaksi akan tercatat atas nama pemilik PIN itu (bukan akun login).
        </p>

        <form action="{{ route('kasir.pin.unlock.submit') }}" method="POST" class="space-y-4" autocomplete="off">
            @csrf
            <div>
                <label class="form-label" for="pin">PIN kasir (4–6 digit)</label>
                <input
                    type="password"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    name="pin"
                    id="pin"
                    class="form-input text-center text-2xl tracking-[0.4em] font-bold"
                    maxlength="6"
                    minlength="4"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="••••"
                >
            </div>
            <button type="submit" class="btn-primary w-full py-3">Buka Kasir</button>
        </form>

        <div class="mt-5 space-y-2 text-center text-xs text-slate-500">
            <p>Login stasiun: <span class="font-semibold text-slate-700">{{ $currentUser->name }}</span></p>
            @if (! $hasOwnPin)
                <p class="text-amber-700">Anda belum punya PIN. <a href="{{ route('pin.edit') }}" class="font-semibold text-brand-600 underline">Buat PIN dulu</a></p>
            @else
                <p><a href="{{ route('pin.edit') }}" class="font-medium text-brand-600 hover:underline">Kelola PIN saya</a></p>
            @endif
            <form action="{{ route('logout') }}" method="POST" class="pt-2">
                @csrf
                <button type="submit" class="text-slate-400 hover:text-slate-700">Keluar akun</button>
            </form>
        </div>
    </div>
</body>
</html>
