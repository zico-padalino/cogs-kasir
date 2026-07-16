@extends('layouts.attendance')

@section('title', 'Absensi')

@section('vite')
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/attendance-scan.js'])
@endsection

@section('content')
    <div
        class="scan-card"
        data-attendance-scan
        data-employees='@json($employees)'
        data-clock-in="{{ $settings['clock_in'] }}"
        data-clock-out="{{ $settings['clock_out'] }}"
        data-has-location="{{ $settings['has_location'] ? '1' : '0' }}"
    >
        <header class="scan-head">
            <div class="scan-mark" aria-hidden="true">{{ \App\Support\ShopSettings::initial() }}</div>
            <div class="min-w-0">
                <p class="scan-eyebrow">Absensi QR</p>
                <h1 class="scan-title">{{ $shopName }}</h1>
                <p class="scan-date">{{ $nowLabel }}</p>
            </div>
        </header>

        <div class="scan-clock" aria-live="polite">
            <p class="scan-clock-time" data-scan-clock>--</p>
            <p class="scan-clock-meta">
                Masuk <strong>{{ $settings['clock_in'] }}</strong>
                <span aria-hidden="true">·</span>
                Pulang <strong>{{ $settings['clock_out'] }}</strong>
            </p>
        </div>

        @if (session('success'))
            <div class="scan-alert scan-alert-ok">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="scan-alert scan-alert-warn">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="scan-alert scan-alert-error">{{ $errors->first() }}</div>
        @endif

        @unless ($settings['has_location'])
            <div class="scan-alert scan-alert-warn">Lokasi toko belum diatur admin — absensi belum bisa dikirim.</div>
        @endunless

        <form action="{{ route('attendance.scan.store') }}" method="POST" class="scan-form" data-scan-form>
            @csrf
            <input type="hidden" name="latitude" data-scan-lat>
            <input type="hidden" name="longitude" data-scan-lng>
            <input type="hidden" name="photo" data-scan-photo>
            <input type="hidden" name="mode" value="check_in" data-scan-mode>

            <label class="form-label" for="employee_id">Nama pegawai</label>
            <select
                id="employee_id"
                name="employee_id"
                class="form-input scan-select"
                required
                data-scan-employee
            >
                <option value="">— Pilih nama —</option>
                @foreach ($employees as $row)
                    <option
                        value="{{ $row['id'] }}"
                        data-action="{{ $row['action'] }}"
                        @selected((string) old('employee_id') === (string) $row['id'])
                    >
                        {{ $row['name'] }} ({{ $row['code'] }})
                    </option>
                @endforeach
            </select>

            <p class="scan-mode-pill" data-scan-mode-label>Pilih pegawai dulu</p>

            <div class="scan-camera-wrap">
                <video data-scan-video class="scan-video" playsinline muted autoplay></video>
                <canvas data-scan-canvas class="hidden"></canvas>
                <div class="scan-camera-oval" aria-hidden="true"></div>
                <p class="scan-camera-caption">Selfie untuk bukti absen</p>
            </div>

            <p class="scan-gps" data-scan-gps>Membaca lokasi GPS…</p>

            <button type="submit" class="btn-primary w-full py-3" data-scan-submit disabled>
                Absen
            </button>
        </form>
    </div>
@endsection
