@extends('layouts.kasir')

@section('title', 'Barcode '.$table->label)
@section('heading', 'Barcode Meja')

@section('content')
    <div class="table-barcode-print mx-auto max-w-sm text-center" id="table-barcode-print">
        <p class="text-xs uppercase tracking-widest text-slate-500">Scan untuk Pesan</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $table->label }}</h1>
        <p class="text-sm text-slate-500">Meja #{{ $table->table_number }}</p>

        <div class="mx-auto mt-6 inline-flex rounded-2xl border-2 border-slate-200 bg-white p-4 shadow-sm">
            <canvas
                data-table-qr-url="{{ $table->orderUrl() }}"
                data-table-qr-size="240"
                width="240"
                height="240"
                aria-label="QR Code {{ $table->label }}"
            ></canvas>
        </div>

        <p class="mt-4 break-all px-2 text-xs text-slate-500">{{ $table->orderUrl() }}</p>
        <p class="mt-6 text-sm text-slate-600">Tempel di meja · Pelanggan scan → pesan → bayar di kasir</p>
    </div>

    <div class="form-actions mt-6">
        <button type="button" onclick="window.print()" class="btn-primary w-full sm:w-auto">Cetak Barcode</button>
        <a href="{{ route('kasir.tables') }}" class="btn-secondary w-full sm:w-auto">← Kembali</a>
    </div>

    <style>
        @media print {
            header, #bottom-nav, .bottom-nav-spacer, .btn-primary, .btn-secondary { display: none !important; }
            main, .app-scroll { padding: 0 !important; overflow: visible !important; }
            #table-barcode-print { padding: 1rem; }
        }
    </style>
@endsection
