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
<body class="login-page" data-kasir-notifications data-kasir-poll-url="{{ route('kasir.pending.poll') }}" data-kasir-poll-interval="{{ config('pos.notifications.poll_interval_seconds', 5) }}" data-kasir-auto-load="0" data-kasir-pin-poll-only="1">
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
                <p class="login-shop-title">PIN menentukan siapa yang melayani</p>
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

        <div class="mb-4 space-y-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-relaxed text-slate-600">
            <p><strong>Login stasiun</strong> boleh akun siapa saja (contoh: {{ $currentUser->name }}).</p>
            <p><strong>PIN</strong> memakai PIN pegawai yang sedang bertugas — nama di kasir & struk mengikuti pegawai itu, bukan akun login.</p>
        </div>

        <form action="{{ route('kasir.pin.unlock.submit') }}" method="POST" class="space-y-4" autocomplete="off" id="kasir-pin-form">
            @csrf
            <div>
                <label class="form-label" for="pin">PIN pegawai (4–6 digit)</label>
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
                <p class="mt-2 text-center text-xs text-slate-500">Isi PIN — otomatis masuk tanpa klik tombol</p>
            </div>
            <button type="submit" class="btn-primary w-full py-3" id="kasir-pin-submit">Buka Kasir</button>
        </form>

        <div class="mt-5 space-y-2 text-center text-xs text-slate-500">
            <p>Stasiun aktif: <span class="font-semibold text-slate-700">{{ $currentUser->name }}</span></p>
            <p class="text-slate-400">PIN dibuat di Admin → Data Karyawan</p>
        </div>

        <div class="mt-5 border-t border-slate-200 pt-4">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="btn-outline w-full min-h-11 border-slate-300 text-slate-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                >
                    Keluar / Logout
                </button>
            </form>
            <p class="mt-2 text-center text-[11px] text-slate-400">Keluar dari akun login stasiun ini</p>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('kasir-pin-form');
            var input = document.getElementById('pin');
            var button = document.getElementById('kasir-pin-submit');
            if (! form || ! input) return;

            var timer = null;
            var submitting = false;

            function onlyDigits(value) {
                return String(value || '').replace(/\D+/g, '').slice(0, 6);
            }

            function submitPin() {
                if (submitting) return;
                var pin = onlyDigits(input.value);
                if (pin.length < 4) return;

                submitting = true;
                input.value = pin;
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Membuka…';
                }
                form.submit();
            }

            input.addEventListener('input', function () {
                var pin = onlyDigits(input.value);
                if (input.value !== pin) {
                    input.value = pin;
                }

                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }

                if (pin.length === 6) {
                    submitPin();
                    return;
                }

                if (pin.length >= 4) {
                    timer = setTimeout(submitPin, 450);
                }
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (timer) clearTimeout(timer);
                    submitPin();
                }
            });
        })();
    </script>
</body>
</html>
