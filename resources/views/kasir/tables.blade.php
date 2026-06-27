@extends('layouts.kasir')

@section('title', 'Meja & Barcode')
@section('heading', 'Meja & Barcode')

@section('content')
    <div class="page-toolbar">
        <div>
            <h1 class="hidden text-2xl font-bold md:block">Meja & Barcode</h1>
            <p class="text-sm text-slate-500">Setiap meja punya QR/barcode menu sendiri. Pelanggan scan → pesan → bayar di kasir.</p>
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
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($tables as $table)
                        <div class="table-qr-card">
                            <div class="table-qr-card-head">
                                <div>
                                    <p class="font-bold text-slate-900">{{ $table->label }}</p>
                                    <p class="text-xs text-slate-500">Meja #{{ $table->table_number }}</p>
                                </div>
                                @if ($table->open_orders_count > 0)
                                    <span class="badge badge-amber">{{ $table->open_orders_count }} aktif</span>
                                @else
                                    <span class="badge badge-green">Kosong</span>
                                @endif
                            </div>

                            <div class="table-qr-wrap">
                                <canvas
                                    data-table-qr-url="{{ $table->orderUrl() }}"
                                    data-table-qr-size="180"
                                    width="180"
                                    height="180"
                                    aria-label="QR Code {{ $table->label }}"
                                ></canvas>
                            </div>

                            <p class="table-qr-url">{{ $table->orderUrl() }}</p>

                            <div class="table-qr-actions">
                                <a href="{{ $table->orderUrl() }}" target="_blank" rel="noopener" class="btn-outline btn-sm w-full justify-center">
                                    Buka Menu Meja
                                </a>
                                <a href="{{ route('kasir.tables.barcode', $table) }}" class="btn-primary btn-sm w-full justify-center">
                                    Cetak Barcode
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="table-card">
                    <div class="empty-state">
                        <p>Belum ada meja.</p>
                        <p class="empty-hint">Tambahkan meja di form kiri atau jalankan seeder PosTableSeeder</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
