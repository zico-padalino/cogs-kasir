@extends('layouts.kasir')

@section('title', 'Meja & Barcode')
@section('heading', 'Meja & Barcode')

@section('content')
    <div class="page-toolbar">
        <div>
            <h1 class="hidden text-2xl font-bold md:block">Meja & Barcode</h1>
            <p class="text-sm text-slate-500">Satu barcode untuk seluruh toko. Pelanggan scan → isi nama & pilih meja → pesan → bayar di kasir.</p>
        </div>
        <a href="{{ route('kasir.barcode') }}" class="btn-primary shrink-0">Cetak Barcode</a>
    </div>

    <div class="card mb-6">
        <div class="table-qr-card-head mb-4">
            <div>
                <p class="font-bold text-slate-900">Barcode Pesanan</p>
                <p class="text-xs text-slate-500">{{ $shopName }} · satu QR untuk semua meja</p>
            </div>
        </div>

        <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-8">
            <div class="table-qr-wrap shrink-0">
                <canvas
                    data-table-qr-url="{{ $orderUrl }}"
                    data-table-qr-size="220"
                    width="220"
                    height="220"
                    aria-label="QR Code pesanan"
                ></canvas>
            </div>

            <div class="min-w-0 flex-1 space-y-3 text-center sm:text-left">
                <p class="break-all text-xs text-slate-500">{{ $orderUrl }}</p>
                <a href="{{ $orderUrl }}" target="_blank" rel="noopener" class="btn-outline btn-sm inline-flex justify-center">
                    Buka Menu Pelanggan
                </a>
                <p class="text-sm text-slate-600">Tempel di kasir atau pintu masuk. Tiap HP mendapat nomor pesanan & nama pemesan sendiri.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-1">
            <h2 class="mb-4 text-lg font-semibold">Tambah Meja</h2>
            <form action="{{ route('kasir.tables.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">No. Meja</label>
                    <input type="text" name="table_number" class="form-input" required placeholder="01" inputmode="numeric">
                </div>
                <div>
                    <label class="form-label">Label</label>
                    <input type="text" name="label" class="form-input" required placeholder="Meja 1">
                </div>
                <button type="submit" class="btn-primary w-full">Simpan Meja</button>
            </form>
        </div>

        <div class="lg:col-span-2">
            @if ($tables->isNotEmpty())
                <div class="card">
                    <h2 class="mb-4 text-lg font-semibold">Daftar Meja</h2>
                    <div class="divide-y divide-slate-100">
                        @foreach ($tables as $table)
                            <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $table->label }}</p>
                                    <p class="text-xs text-slate-500">Meja #{{ $table->table_number }}</p>
                                </div>
                                @if ($table->open_orders_count > 0)
                                    <span class="badge badge-amber">{{ $table->open_orders_count }} pesanan aktif</span>
                                @else
                                    <span class="badge badge-green">Kosong</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="table-card">
                    <div class="empty-state">
                        <p>Belum ada meja.</p>
                        <p class="empty-hint">Tambahkan meja di form kiri agar pelanggan bisa memilih nomor meja saat pesan.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
