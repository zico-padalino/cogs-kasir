@extends('layouts.attendance')

@section('title', $mode === 'check_out' ? 'Absen Pulang' : 'Absen Masuk')

@section('content')
    <div class="login-card max-w-md" data-attendance-check data-mode="{{ $mode }}">
        <div class="login-brand">
            <div class="login-logo-fallback">{{ \App\Support\ShopSettings::initial() }}</div>
            <div class="login-brand-copy">
                <h1 class="login-shop-name">{{ $mode === 'check_out' ? 'Absen Pulang' : 'Absen Masuk' }}</h1>
                <p class="login-shop-title">{{ $employee?->name ?? $user->name }} · {{ now()->translatedFormat('d M Y') }}</p>
            </div>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="auth-alert-error mb-4" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 space-y-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
            <p>Jam masuk: <strong>{{ $settings['clock_in'] }}</strong> · Jam pulang: <strong>{{ $settings['clock_out'] }}</strong></p>
            <p>Radius lokasi: <strong>{{ number_format($settings['radius_meters'], 0) }} m</strong></p>
            @unless ($settings['has_location'])
                <p class="font-semibold text-amber-700">Lokasi toko belum diatur — hubungi admin.</p>
            @endunless
        </div>

        <div class="attendance-gps-panel">
            <div class="attendance-gps-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-8 w-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z" />
                    <circle cx="12" cy="10" r="2.5" />
                </svg>
            </div>
            <p class="attendance-gps-title">Absen dengan lokasi GPS</p>
            <p class="attendance-status" data-attendance-status>Membaca lokasi…</p>
        </div>

        <form
            action="{{ $mode === 'check_out' ? route('attendance.check-out.store') : route('attendance.check-in.store') }}"
            method="POST"
            class="mt-4 space-y-3"
            data-attendance-form
        >
            @csrf
            <input type="hidden" name="latitude" data-attendance-lat>
            <input type="hidden" name="longitude" data-attendance-lng>

            <button type="submit" class="btn-primary w-full py-3" data-attendance-submit disabled>
                {{ $mode === 'check_out' ? 'Absen Pulang' : 'Absen Masuk' }}
            </button>
        </form>

        <div class="mt-4 text-center">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-slate-700">Keluar akun</button>
            </form>
        </div>
    </div>
@endsection
