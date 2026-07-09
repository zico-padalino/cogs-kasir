@extends('layouts.app')

@section('title', 'Produksi')
@section('heading', 'Langkah 4: Produksi')
@section('subheading', 'Catat berapa menu yang dibuat — modal dihitung otomatis')

@section('content')
    <div class="module-page module-step-4">
        <x-module-form-card :step="4" title="Catat Produksi" description="Pilih menu dan jumlah yang dibuat. Bahan diambil dari resep, modal langsung terhitung.">
            @if ($products->isEmpty())
                <div class="module-empty !py-8">
                    <span class="module-empty__icon" aria-hidden="true">👨‍🍳</span>
                    <p class="module-empty__title">Belum ada menu</p>
                    <p class="module-empty__hint">Tambah menu & resep dulu sebelum catat produksi.</p>
                    <a href="{{ route('products.index') }}" class="btn-primary btn-sm mt-3 inline-flex">Ke Menu & Resep →</a>
                </div>
            @else
                <form action="{{ route('production-orders.store') }}" method="POST" class="overhead-add-form">
                    @csrf
                    <input type="hidden" name="langsung_hitung" value="1">

                    <div class="field-name sm:col-span-2">
                        <label class="form-label">Menu yang dibuat</label>
                        <select name="product_id" class="form-input text-base" required>
                            <option value="">Pilih menu...</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-rate">
                        <label class="form-label">Jumlah dibuat</label>
                        <input type="number" name="quantity_planned" class="form-input text-lg font-semibold" min="1" step="1" value="{{ old('quantity_planned', 10) }}" required>
                    </div>
                    <div class="field-submit">
                        <button type="submit" class="btn-primary w-full py-3 text-base font-semibold lg:px-3">Catat & Hitung Modal</button>
                    </div>
                </form>

                <details class="rounded-xl border-2 border-violet-100 bg-violet-50/40 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-violet-800">Upah kerja & mesin (kalau perlu)</summary>
                    <form action="{{ route('production-orders.store') }}" method="POST" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="langsung_hitung" value="1">
                        <select name="product_id" class="form-input text-sm" required>
                            <option value="">Pilih menu...</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="quantity_planned" class="form-input text-sm" min="1" value="10" required placeholder="Jumlah">
                        <input type="number" name="machine_hours" class="form-input text-sm" min="0" step="0.1" value="0" placeholder="Jam mesin dipakai">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <input type="text" name="labors[0][description]" class="form-input text-sm" value="Pekerja" placeholder="Nama pekerjaan">
                            <input type="number" name="labors[0][labor_hours]" class="form-input text-sm" step="0.1" value="0" placeholder="Jam kerja">
                            <x-rupiah-input name="labors[0][hourly_rate]" :value="0" placeholder="Upah/jam" class="text-sm" />
                        </div>
                        <button type="submit" class="btn-secondary btn-sm">Catat dengan upah</button>
                    </form>
                </details>
            @endif
        </x-module-form-card>

        <x-table-card :step="4" title="Riwayat Produksi" :subtitle="$orders->total() . ' catatan'">
            @if ($orders->isNotEmpty())
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th class="col-actions">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="font-bold text-slate-900">{{ $order->product->name }}</td>
                                <td>{{ number_format($order->quantity_planned, 0) }} {{ $order->product->unit }}</td>
                                <td>
                                    @php
                                        $badges = [
                                            'draft' => ['Belum dihitung', 'badge-slate'],
                                            'in_progress' => ['Sedang jalan', 'badge-blue'],
                                            'completed' => ['Selesai', 'badge-green'],
                                        ];
                                        [$label, $badgeClass] = $badges[$order->status->value] ?? ['?', 'badge-slate'];
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                                </td>
                                <td class="col-actions">
                                    <a href="{{ route('production-orders.show', $order) }}" class="btn-secondary btn-sm">Lihat</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-slot:footer>
                    <p class="text-sm font-medium text-slate-600">Modal sudah terhitung? Tentukan harga jual.</p>
                    <a href="{{ route('menu-pricing.index') }}" class="btn-primary btn-sm">Ke Harga Jual →</a>
                </x-slot:footer>
            @else
                <div class="module-empty">
                    <span class="module-empty__icon" aria-hidden="true">📊</span>
                    <p class="module-empty__title">Belum ada produksi</p>
                    <p class="module-empty__hint">Isi form di atas untuk catat produksi pertama.</p>
                </div>
            @endif
        </x-table-card>

        @if ($orders->hasPages())
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
