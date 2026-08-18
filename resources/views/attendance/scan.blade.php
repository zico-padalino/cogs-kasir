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
            <div class="min-w-0 flex-1">
                <p class="scan-eyebrow">Absensi QR</p>
                <h1 class="scan-title">{{ $shopName }}</h1>
                <p class="scan-date">{{ $nowLabel }}</p>
            </div>
            <div class="scan-clock-mini" aria-live="polite">
                <p class="scan-clock-time" data-scan-clock>--</p>
            </div>
        </header>

        <p class="scan-hours">
            Masuk <strong>{{ $settings['clock_in'] }}</strong>
            <span aria-hidden="true">·</span>
            Pulang <strong>{{ $settings['clock_out'] }}</strong>
        </p>

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
            <input type="hidden" name="mode" value="{{ old('mode', 'check_in') }}" data-scan-mode>

            <div>
                <p class="form-label">Jenis absen</p>
                <div class="scan-mode-toggle" role="group" aria-label="Jenis absen">
                    <button
                        type="button"
                        class="scan-mode-btn is-active"
                        data-scan-mode-btn="check_in"
                    >
                        Absen Masuk
                    </button>
                    <button
                        type="button"
                        class="scan-mode-btn"
                        data-scan-mode-btn="check_out"
                    >
                        Absen Pulang
                    </button>
                </div>
            </div>

            <div>
                <label class="form-label" for="employee_id">Nama pegawai</label>
                <select
                    id="employee_id"
                    name="employee_id"
                    class="form-input scan-select"
                    required
                    data-scan-employee
                    data-searchable-select
                    data-search-placeholder="— Pilih nama —"
                    data-search-input-placeholder="Cari nama..."
                >
                    <option value="">— Pilih nama —</option>
                    @php
                        $prefillId = old('employee_id', $selectedEmployeeId ?? null);
                    @endphp
                    @foreach ($employees as $row)
                        <option
                            value="{{ $row['id'] }}"
                            data-action="{{ $row['action'] }}"
                            data-actions="{{ implode(',', $row['actions'] ?? []) }}"
                            data-missed-checkout="{{ ! empty($row['missed_checkout']) ? '1' : '0' }}"
                            data-clock-in="{{ $row['clock_in'] ?? '' }}"
                            data-clock-out="{{ $row['clock_out'] ?? '' }}"
                            data-is-off="{{ ! empty($row['is_off']) ? '1' : '0' }}"
                            @selected((string) $prefillId === (string) $row['id'])
                        >
                            {{ $row['name'] }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500" data-scan-employee-hint>
                    Pilih jenis absen dulu, lalu pilih nama.
                </p>
            </div>

            <p class="scan-mode-pill" data-scan-mode-label>Pilih Absen Masuk atau Absen Pulang</p>

            <div class="scan-alert scan-alert-warn hidden" data-scan-missed-warn role="status">
                Anda belum absen pulang shift sebelumnya.
                Jika lanjut <strong>Absen Masuk</strong>, ketidakhadiran absen pulang akan tercatat.
            </div>

            <div class="scan-camera">
                <div class="scan-camera-preview">
                    <video data-scan-video class="scan-video" playsinline muted autoplay></video>
                    <canvas data-scan-canvas class="hidden"></canvas>
                </div>
                <p class="scan-camera-hint">Ambil selfie sebagai bukti absen. Tidak perlu daftar wajah.</p>
            </div>

            <div class="scan-gps-panel" data-scan-gps-panel>
                <p class="scan-gps" data-scan-gps>Membaca lokasi GPS…</p>
                <button type="button" class="scan-gps-enable btn-secondary w-full" data-scan-gps-enable>
                    Izinkan lokasi
                </button>
                <p class="scan-gps-hint">Di Safari/iPhone: ketuk tombol di atas, lalu pilih <strong>Izinkan</strong> saat diminta.</p>
            </div>

            <button type="submit" class="btn-primary w-full py-3.5 text-base" data-scan-submit disabled>
                Absen Masuk
            </button>
        </form>
    </div>
@endsection
