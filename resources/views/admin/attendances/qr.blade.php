@extends('layouts.admin')

@section('title', 'QR Absensi')
@section('heading', 'QR Absensi')

@section('content')
    <div class="barcode-print-page attendance-qr-page">
        <p class="barcode-print-hint no-print">
            Tampilkan QR ini di toko. Pegawai scan dari HP → isi nama, selfie, lalu absen (GPS wajib sesuai koordinat).
        </p>

        <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 no-print sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jam masuk</p>
                <p class="font-semibold text-slate-800">{{ $settings['clock_in'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jam pulang</p>
                <p class="font-semibold text-slate-800">{{ $settings['clock_out'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Radius GPS</p>
                <p class="font-semibold text-slate-800">{{ number_format($settings['radius_meters'], 0) }} m</p>
            </div>
        </div>

        <div
            class="barcode-print-card"
            id="attendance-qr-print"
            data-shop-name="{{ $shopName }}"
            data-shop-title="{{ $shopTitle }}"
            data-scan-url="{{ $scanUrl }}"
        >
            <div class="barcode-print-mark" aria-hidden="true">QR</div>

            <p class="barcode-print-eyebrow">Scan untuk absen</p>
            <h1 class="barcode-print-shop">{{ $shopName }}</h1>
            @if (! empty($shopTitle))
                <p class="barcode-print-tagline">{{ $shopTitle }}</p>
            @endif

            <div class="barcode-print-qr-frame">
                <canvas
                    class="barcode-print-qr-canvas"
                    data-table-qr-url="{{ $scanUrl }}"
                    data-table-qr-size="200"
                    data-table-qr-margin="1"
                    data-table-qr-ecc="H"
                    width="200"
                    height="200"
                    aria-label="QR Code absensi"
                ></canvas>
            </div>

            <p class="barcode-print-cta">Arahkan kamera HP ke kode ini</p>
            <p class="barcode-print-flow">
                <span>Scan</span>
                <span aria-hidden="true">→</span>
                <span>Pilih nama</span>
                <span aria-hidden="true">→</span>
                <span>Selfie + GPS</span>
            </p>

            <p class="mt-4 break-all px-4 text-center text-[11px] text-slate-400 no-print">{{ $scanUrl }}</p>
        </div>

        <div class="barcode-print-actions form-actions no-print">
            <button type="button" data-attendance-qr-download class="btn-primary w-full sm:w-auto">
                Unduh gambar PNG
            </button>
            <button type="button" data-attendance-qr-print class="btn-secondary w-full sm:w-auto">
                Cetak / Simpan PDF
            </button>
            <a href="{{ route('admin.attendances.index') }}" class="btn-secondary w-full sm:w-auto">← Daftar Absensi</a>
        </div>
    </div>
@endsection
