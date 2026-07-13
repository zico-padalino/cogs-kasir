@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    @php
        $shopName = config('pos.shop_name', 'Point of Sale');
        $shopTitle = config('pos.shop_title') ?: 'Masuk untuk mengelola toko Anda';
        $loginLogo = \App\Support\ShopSettings::logoUrl();
        $initial = \App\Support\ShopSettings::initial();
    @endphp

    <div class="login-page">
        <div class="login-glow" aria-hidden="true"></div>

        <div class="login-card">
            <div class="login-brand">
                @if ($loginLogo)
                    <img src="{{ $loginLogo }}" alt="{{ $shopName }}" class="login-logo-img">
                @else
                    <div class="login-logo-fallback" aria-hidden="true">{{ $initial }}</div>
                @endif

                <div class="login-brand-copy">
                    <h1 class="login-shop-name">{{ $shopName }}</h1>
                    <p class="login-shop-title">{{ $shopTitle }}</p>
                </div>
            </div>

            <div class="login-divider" aria-hidden="true"></div>

            <div class="login-form-head">
                <h2 class="login-form-title">Masuk</h2>
                <p class="login-form-sub">Gunakan email dan password akun Anda</p>
            </div>

            @if ($errors->any())
                <div class="auth-alert-error mb-5" role="alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login.store') }}" method="POST" class="login-form">
                @csrf

                <div>
                    <label class="form-label" for="email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-input"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@email.com"
                    >
                </div>

                <div>
                    <label class="form-label" for="password">Password</label>
                    <div class="password-field">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-input password-field-input"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                        <button
                            type="button"
                            class="password-field-toggle"
                            data-password-toggle
                            aria-label="Tampilkan password"
                        >
                            <span class="password-field-icon" data-password-icon-show>
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/>
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </span>
                            <span class="password-field-icon hidden" data-password-icon-hide>
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M9.88 9.88A3 3 0 0114.12 14.12M6.1 6.1C4.05 7.57 2.55 9.7 2.25 12c0 0 3.75 6.75 9.75 6.75 1.7 0 3.24-.4 4.55-1.05M17.94 17.94c1.84-1.37 3.2-3.3 3.56-5.94 0 0-3.75-6.75-9.75-6.75-1.05 0-2.04.15-2.95.42"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('remember'))>
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" class="btn-primary login-submit">
                    Masuk
                </button>
            </form>
        </div>
    </div>
@endsection
