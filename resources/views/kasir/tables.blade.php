@extends('layouts.kasir')

@section('title', 'Meja & Barcode')
@section('heading', 'Meja & Barcode')

@section('content')
    <div class="page-toolbar">
        <div>
            <h1 class="hidden text-2xl font-bold md:block">Meja & Barcode</h1>
            <p class="text-sm text-slate-500">Scan QR/barcode meja untuk pemesanan online pelanggan</p>
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
            <x-table-card title="Daftar Meja" subtitle="Bagikan link/QR ke pelanggan">
                @if ($tables->isNotEmpty())
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Meja</th>
                                <th>Link Pemesanan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tables as $table)
                                <tr>
                                    <td>
                                        <p class="font-semibold">{{ $table->label }}</p>
                                        <p class="text-xs cell-muted">#{{ $table->table_number }}</p>
                                    </td>
                                    <td>
                                        <a href="{{ $table->orderUrl() }}" target="_blank" class="break-all text-xs font-medium text-brand-600 hover:underline">
                                            {{ $table->orderUrl() }}
                                        </a>
                                        <p class="mt-1 text-[10px] cell-muted">Token: {{ Str::limit($table->barcode_token, 18) }}</p>
                                    </td>
                                    <td>
                                        @if ($table->open_orders_count > 0)
                                            <span class="badge badge-amber">{{ $table->open_orders_count }} pesanan aktif</span>
                                        @else
                                            <span class="badge badge-green">Kosong</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty-state">
                        <p>Belum ada meja.</p>
                        <p class="empty-hint">Tambahkan meja atau jalankan database/kasir.sql</p>
                    </div>
                @endif
            </x-table-card>
        </div>
    </div>
@endsection
