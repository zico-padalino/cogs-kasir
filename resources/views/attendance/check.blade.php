@extends('layouts.attendance')

@section('title', $mode === 'check_out' ? 'Absen Pulang' : 'Absen Masuk')

@section('content')
    <div class="scan-card" data-attendance-check data-mode="{{ $mode }}">
        <header class="scan-head">
            <div class="scan-mark" aria-hidden="true">{{ \App\Support\ShopSettings::initial() }}</div>
            <div class="min-w-0 flex-1">
                <p class="scan-eyebrow">{{ $mode === 'check_out' ? 'Absen Pulang' : 'Absen Masuk' }}</p>
                <h1 class="scan-title">{{ $employee?->name ?? $user->name }}</h1>
                <p class="scan-date">{{ now()->translatedFormat('d M Y') }}</p>
            </div>
        </header>

        @if (session('error'))
            <div class="scan-alert scan-alert-warn">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="scan-alert scan-alert-error">{{ $errors->first() }}</div>
        @endif

        <p class="scan-hours">
            Masuk <strong>{{ $settings['clock_in'] }}</strong>
            <span aria-hidden="true">·</span>
            Pulang <strong>{{ $settings['clock_out'] }}</strong>
            <span aria-hidden="true">·</span>
            Radius <strong>{{ number_format($settings['radius_meters'], 0) }} m</strong>
        </p>

        @unless ($settings['has_location'])
            <div class="scan-alert scan-alert-warn">Lokasi toko belum diatur — hubungi admin.</div>
        @endunless

        <div class="attendance-gps-panel mt-4">
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
            class="scan-form"
            data-attendance-form
        >
            @csrf
            <input type="hidden" name="latitude" data-attendance-lat>
            <input type="hidden" name="longitude" data-attendance-lng>

            <button type="submit" class="btn-primary w-full py-3.5 text-base" data-attendance-submit disabled>
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
