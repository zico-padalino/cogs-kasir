@extends('layouts.app')

@section('title', 'Stok Rusak / Gagal')
@section('heading', 'Stok Rusak / Gagal')
@section('subheading', 'Catat produk rusak atau gagal — stok inventori & menu kasir ikut tersinkron')

@section('content')
    <div class="module-page">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Periode</p>
                <p class="mt-1 text-sm font-semibold text-espresso">{{ $label }}</p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Total qty</p>
                <p class="mt-1 text-lg font-bold text-espresso">{{ number_format($totalQty, 2) }}</p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm col-span-2 sm:col-span-1">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Nilai rugi</p>
                <p class="mt-1 text-lg font-bold text-espresso">{{ $format::rupiah($totalCost) }}</p>
            </div>
        </div>

        <div class="card mb-4">
            <h2 class="mb-3 font-display text-base font-semibold text-espresso">Catat rusak / gagal</h2>
            <form action="{{ route('stock-wastes.store') }}" method="POST" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label class="form-label">Produk / bahan</label>
                    <select name="product_id" class="form-input" required>
                        <option value="">— Pilih —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                {{ $product->name }} ({{ $product->unit }})
                                @if ($product->is_menu_item) · menu @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Jumlah</label>
                    <input type="number" step="any" min="0.000001" name="quantity" class="form-input" required value="{{ old('quantity') }}" placeholder="1">
                </div>
                <div>
                    <label class="form-label">Alasan</label>
                    <select name="reason" class="form-input" required>
                        @foreach ($reasons as $key => $labelReason)
                            <option value="{{ $key }}" @selected(old('reason', 'rusak') === $key)>{{ $labelReason }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">ID order (opsional)</label>
                    <input type="number" name="pos_order_id" class="form-input" value="{{ old('pos_order_id') }}" placeholder="Mis. dari riwayat kasir">
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-input" value="{{ old('note') }}" placeholder="Contoh: tumpah / reject dapur">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary w-full sm:w-auto">Simpan pencatatan</button>
                </div>
            </form>
        </div>

        <div class="card mb-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="form-label">Periode</label>
                    <select name="period" class="form-input">
                        <option value="day" @selected($period === 'day')>Harian</option>
                        <option value="month" @selected($period === 'month')>Bulanan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tanggal / bulan</label>
                    <input type="{{ $period === 'month' ? 'month' : 'date' }}" name="date" class="form-input" value="{{ $period === 'month' ? substr($date, 0, 7) : substr($date, 0, 10) }}">
                </div>
                <div>
                    <label class="form-label">Alasan</label>
                    <select name="reason" class="form-input">
                        <option value="">Semua</option>
                        @foreach ($reasons as $key => $labelReason)
                            <option value="{{ $key }}" @selected($reason === $key)>{{ $labelReason }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-secondary">Filter</button>
            </form>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <div>
                    <h2 class="font-display text-base font-semibold text-espresso">Riwayat</h2>
                    <p class="text-xs text-slate-500">{{ $wastes->count() }} catatan</p>
                </div>
            </div>
            <div class="table-scroll">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Alasan</th>
                            <th>Nilai</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($wastes as $waste)
                            <tr>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $waste->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="font-medium text-slate-900">{{ $waste->product?->name ?? '—' }}</td>
                                <td>{{ number_format((float) $waste->quantity, 2) }} {{ $waste->product?->unit }}</td>
                                <td><span class="badge-amber">{{ $waste->reasonLabel() }}</span></td>
                                <td>{{ $format::rupiah($waste->total_cost) }}</td>
                                <td class="text-xs text-slate-500">{{ $waste->note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm text-slate-500">Belum ada pencatatan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
