@extends('layouts.app')

@section('title', 'Harga Jual')
@section('heading', 'Langkah 5: Harga Jual Menu')
@section('subheading', 'Tentukan harga jual berdasarkan modal — menu siap tampil di Kasir')

@section('content')
    <div class="module-page module-step-5">
        <x-module-tip :step="5" title="Cara pakai">
            <strong>Modal</strong> = biaya bikin 1 porsi. Isi <strong>harga jual</strong>, centang <strong>tampil di Kasir</strong>, lalu simpan.
            <span class="mt-1 block text-xs text-slate-500">Tips untung 30%: harga jual ≈ modal ÷ 0,7 (modal Rp 7.000 → jual ~Rp 10.000).</span>
        </x-module-tip>

        @if ($items->isEmpty())
            <div class="module-empty">
                <span class="module-empty__icon" aria-hidden="true">💰</span>
                <p class="module-empty__title">Belum ada menu</p>
                <p class="module-empty__hint">Tambah menu & resep, lalu catat produksi supaya modal terisi.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('products.index') }}" class="btn-secondary btn-sm">Ke Menu & Resep</a>
                    <a href="{{ route('production-orders.index') }}" class="btn-primary btn-sm">Catat Produksi</a>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($items as $item)
                    @php
                        $product = $item['product'];
                        $modal = $item['modal'];
                        $belumModal = $modal <= 0;
                    @endphp
                    <div class="module-pricing-card {{ $belumModal ? 'is-warning' : '' }}">
                        <form action="{{ route('menu-pricing.update', $product) }}" method="POST" class="space-y-4"
                              data-pricing-form data-modal="{{ $belumModal ? 0 : $modal }}" data-unit="{{ $product->unit }}">
                            @csrf @method('PUT')

                            <div class="module-pricing-card__head">
                                <div class="min-w-0">
                                    <p class="text-lg font-bold text-slate-900">{{ $product->name }}</p>
                                    <p class="text-sm text-slate-500">per {{ $product->unit }}</p>
                                </div>
                                <div class="module-pricing-card__modal {{ $belumModal ? '!bg-amber-600' : '' }}">
                                    <p class="module-pricing-card__modal-label">Modal</p>
                                    @if ($belumModal)
                                        <p class="module-pricing-card__modal-value text-sm">Belum dihitung</p>
                                    @else
                                        <p class="module-pricing-card__modal-value">{{ $format::rupiah($modal, 0) }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($belumModal)
                                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    Catat produksi dulu supaya modal terisi.
                                    <a href="{{ route('production-orders.index') }}" class="font-bold underline">Ke Produksi →</a>
                                </p>
                            @endif

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="form-label">Harga jual (Rp)</label>
                                    <x-rupiah-input name="selling_price" :value="old('selling_price', $product->selling_price)" placeholder="15.000" required />
                                </div>
                                <div class="flex flex-col justify-end">
                                    @if (! $belumModal)
                                        <div class="module-pricing-card__profit" data-pricing-profit>
                                            Untung per {{ $product->unit }}:
                                            <strong data-pricing-amount class="{{ $item['untung'] >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $format::rupiah($item['untung'], 0) }}</strong>
                                            <span class="text-slate-600" data-pricing-percent>({{ $format::number($item['persen_untung'], 1) }}%)</span>
                                        </div>
                                    @else
                                        <p class="text-sm text-slate-500">Untung muncul otomatis setelah modal terisi.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <input type="checkbox" name="is_menu_item" value="1" class="rounded" @checked(old('is_menu_item', $product->is_menu_item))>
                                    <span class="text-sm font-medium">Tampilkan di Kasir</span>
                                </label>
                                <button type="submit" class="btn-primary px-5 py-2.5 font-semibold">Simpan Harga</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            <x-table-card :step="5" title="Rincian Modal" subtitle="Detail perhitungan bahan & biaya lain">
                <div class="p-4 sm:p-5">
                    <p class="text-sm text-slate-600">Lihat riwayat lengkap perhitungan modal dari setiap produksi.</p>
                    <a href="{{ route('cogs.history') }}" class="btn-secondary btn-sm mt-3 inline-flex">Buka Rincian →</a>
                </div>
            </x-table-card>
        @endif
    </div>
@endsection
