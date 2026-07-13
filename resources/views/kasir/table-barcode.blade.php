@extends('layouts.kasir')

@section('title', 'Barcode Pesanan')
@section('heading', 'Barcode Pesanan')

@section('content')
    <div class="barcode-print-page">
        <p class="barcode-print-hint no-print">
            Ukuran stiker meja — unduh PNG lalu cetak kecil untuk ditempel.
        </p>

        <div
            class="barcode-print-card"
            id="table-barcode-print"
            data-shop-name="{{ $shopName }}"
            data-order-url="{{ $orderUrl }}"
        >
            <div class="barcode-print-mark" aria-hidden="true">QR</div>

            <p class="barcode-print-eyebrow">Scan untuk pesan</p>
            <h1 class="barcode-print-shop">{{ $shopName }}</h1>

            <div class="barcode-print-qr-frame">
                <canvas
                    class="barcode-print-qr-canvas"
                    data-table-qr-url="{{ $orderUrl }}"
                    data-table-qr-size="180"
                    data-table-qr-margin="1"
                    data-table-qr-ecc="H"
                    width="180"
                    height="180"
                    aria-label="QR Code pesanan"
                ></canvas>
            </div>

            <p class="barcode-print-cta">Arahkan kamera ke kode ini</p>
            <p class="barcode-print-flow">
                <span>Scan</span>
                <span aria-hidden="true">→</span>
                <span>Pesan</span>
                <span aria-hidden="true">→</span>
                <span>Bayar di kasir</span>
            </p>
        </div>

        <div class="barcode-print-actions form-actions no-print">
            <button type="button" data-barcode-download class="btn-primary w-full sm:w-auto">
                Unduh Stiker PNG
            </button>
            <a href="{{ route('kasir.tables') }}" class="btn-secondary w-full sm:w-auto">← Kembali</a>
        </div>
    </div>
@endsection
