@extends('layouts.app')

@section('title', 'Harga Jual')
@section('heading', 'Langkah 5: Harga Jual Menu')
@section('subheading', 'Tentukan harga jual berdasarkan modal — menu siap tampil di Kasir')

@section('content')
    <div class="space-y-4">
        <div class="card border-brand-100 bg-brand-50/40 p-4">
            <p class="text-sm text-slate-700">
                <strong>Modal</strong> = biaya bikin 1 porsi (dari produksi).
                Isi <strong>harga jual</strong>, centang <strong>tampil di Kasir</strong>, lalu simpan.
            </p>
            <p class="mt-2 text-xs text-slate-500">
                Tips: mau untung 30%? Harga jual ≈ modal ÷ 0,7. Contoh modal Rp 7.000 → jual sekitar Rp 10.000.
            </p>
        </div>

        @if ($items->isEmpty())
            <div class="card empty-state py-12">
                <p>Belum ada menu.</p>
                <p class="empty-hint">Tambah menu & resep dulu, lalu catat produksi supaya modal terisi.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('products.index') }}" class="btn-secondary btn-sm">Ke Menu & Resep</a>
                    <a href="{{ route('production-orders.index') }}" class="btn-primary btn-sm">Catat Produksi</a>
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($items as $item)
                    @php
                        $product = $item['product'];
                        $modal = $item['modal'];
                        $belumModal = $modal <= 0;
                    @endphp
                    <div class="card p-4 {{ $belumModal ? 'border-amber-200 bg-amber-50/30' : '' }}">
                        <form action="{{ route('menu-pricing.update', $product) }}" method="POST" class="space-y-3">
                            @csrf @method('PUT')

                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $product->unit }}</p>
                                </div>
                                <div class="text-right text-sm">
                                    <p class="text-xs text-slate-500">Modal / porsi</p>
                                    @if ($belumModal)
                                        <p class="font-semibold text-amber-700">Belum dihitung</p>
                                    @else
                                        <p class="font-bold text-slate-900">{{ $format::rupiah($modal, 0) }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($belumModal)
                                <p class="text-xs text-amber-800">
                                    Catat produksi dulu supaya modal terisi otomatis.
                                    <a href="{{ route('production-orders.index') }}" class="font-semibold underline">Ke Produksi →</a>
                                </p>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="form-label">Harga jual (Rp)</label>
                                    <x-rupiah-input name="selling_price" :value="old('selling_price', $product->selling_price)" placeholder="15.000" required />
                                </div>
                                <div class="flex flex-col justify-end">
                                    @if (! $belumModal && (float) $product->selling_price > 0)
                                        @php
                                            $untung = $item['untung'];
                                            $persen = $item['persen_untung'];
                                        @endphp
                                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                            Untung: <strong class="{{ $untung >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $format::rupiah($untung, 0) }}</strong>
                                            <span class="text-slate-500">({{ $format::number($persen, 1) }}%)</span>
                                        </p>
                                    @else
                                        <p class="text-xs text-slate-500">Untung dihitung otomatis setelah harga diisi.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="is_menu_item" value="1" class="rounded" @checked(old('is_menu_item', $product->is_menu_item))>
                                    <span class="text-sm">Tampilkan di Kasir</span>
                                </label>
                                <button type="submit" class="btn-primary btn-sm">Simpan Harga</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800">Mau lihat rincian perhitungan modal?</p>
                        <p class="text-xs text-slate-500">Detail bahan, upah, dan biaya tambahan per produksi.</p>
                    </div>
                    <a href="{{ route('cogs.history') }}" class="btn-secondary btn-sm">Lihat Rincian →</a>
                </div>
            </div>
        @endif
    </div>
@endsection
