@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="auth-shell auth-shell-fintech">
        <div class="auth-brand">
            <div class="auth-brand-inner">
                <div class="auth-logo">P</div>
                <h1 class="auth-brand-title">{{ config('pos.shop_name', 'Point of Sale') }}</h1>
                <p class="auth-brand-text">Masuk dengan akun Anda. Akses modul mengikuti role yang sudah diatur admin.</p>
            </div>
        </div>

        <div class="auth-panel">
            <div class="auth-panel-inner auth-panel-fintech">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900">Selamat datang</h2>
                    <p class="mt-1 text-sm text-slate-500">Masukkan email dan password untuk melanjutkan.</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert-error mb-5">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('login.store') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-input" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>

                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-input" required autocomplete="current-password">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600" @checked(old('remember'))>
                        Ingat saya
                    </label>

                    <button type="submit" class="btn-primary w-full py-3 text-base">
                        Masuk
                    </button>
                </form>

                @if (config('app.debug'))
                    <div class="auth-demo-box mt-6">
                        <p class="font-semibold text-slate-700">Akun demo</p>
                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                            <li><strong>Admin:</strong> admin@local.test / password → panel Admin</li>
                            <li><strong>Hitung Biaya:</strong> cogs@local.test / password → Hitung Biaya</li>
                            <li><strong>Kasir:</strong> kasir@local.test / password → Kasir</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
