@extends('layouts.app')

@section('title', 'Harga Jual')
@section('heading', 'Langkah 4: Harga Jual Menu')
@section('subheading', 'Tentukan harga jual berdasarkan modal — menu siap tampil di Kasir')

@section('content')
    <div class="module-page module-step-4">
        <div class="module-toolbar">
            <p class="module-toolbar__text">Atur harga jual berdasarkan modal — menu siap tampil di Kasir.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('products.index') }}" class="btn-outline btn-sm shrink-0">← Menu & Resep</a>
                <a href="{{ route('materials.index') }}" class="btn-outline btn-sm shrink-0">← Bahan</a>
            </div>
        </div>

        <x-module-tip :step="4" title="Cara pakai">
            Isi <strong>harga jual</strong> langsung, atau isi <strong>persen untung</strong> — yang satu ikut terhitung otomatis.
            <span class="mt-1 block text-xs text-slate-500">Contoh: modal Rp 7.000 + untung 30% → harga jual ~Rp 10.000.</span>
        </x-module-tip>

        @if ($items->isEmpty())
            <div class="module-empty">
                <span class="module-empty__icon" aria-hidden="true">💰</span>
                <p class="module-empty__title">Belum ada menu</p>
                <p class="module-empty__hint">Tambah menu & resep dulu supaya bisa atur harga jual.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('products.index') }}" class="btn-primary btn-sm">Ke Menu & Resep</a>
                </div>
            </div>
        @else
            <div class="pricing-list" data-pricing-list>
                <div class="materials-search mb-3">
                    <input
                        type="search"
                        class="form-input"
                        placeholder="Cari menu..."
                        data-pricing-search
                        autocomplete="off"
                    >
                </div>
                <p class="mb-3 text-xs text-slate-500" data-pricing-count>
                    {{ $items->count() }} menu
                </p>

                <div class="pricing-desktop-grid">
                    @foreach ($items as $item)
                        @php
                            $product = $item['product'];
                            $modal = $item['modal'];
                            $belumModal = $modal <= 0;
                            $persen = old('margin_percent', $item['persen_untung'] > 0 ? $item['persen_untung'] : null);
                        @endphp
                        <div
                            class="module-pricing-card {{ $belumModal ? 'is-warning' : '' }}"
                            data-pricing-card
                            data-search="{{ strtolower($product->name.' '.$product->unit.' '.($product->sku ?? '')) }}"
                        >
                            <form action="{{ route('menu-pricing.update', $product) }}" method="POST" class="space-y-4"
                                  data-pricing-form
                                  data-modal="{{ $belumModal ? 0 : $modal }}"
                                  data-unit="{{ $product->unit }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="pricing_mode" value="price" data-pricing-mode>

                                <div class="module-pricing-card__head">
                                    <div class="min-w-0">
                                        <a
                                            href="{{ route('products.show', $product) }}"
                                            class="text-lg font-bold text-slate-900 hover:text-brand-700 hover:underline"
                                        >
                                            {{ $product->name }}
                                        </a>
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
                                        Modal belum terisi. Lengkapi resep bahan di menu dulu.
                                        <a href="{{ route('products.show', $product) }}" class="font-bold underline">Isi Resep →</a>
                                    </p>
                                @endif

                                <div class="pricing-fields">
                                    <div>
                                        <label class="form-label">Harga jual (Rp)</label>
                                        <x-rupiah-input name="selling_price" :value="old('selling_price', $product->selling_price)" placeholder="15.000" />
                                        <p class="form-hint mt-1">Isi nominal langsung</p>
                                    </div>
                                    <div>
                                        <label class="form-label" for="margin_percent_{{ $product->id }}">Persen untung (%)</label>
                                        <div class="relative">
                                            <input
                                                type="number"
                                                id="margin_percent_{{ $product->id }}"
                                                name="margin_percent"
                                                class="form-input pr-10"
                                                data-pricing-margin
                                                step="0.1"
                                                min="0"
                                                max="99.9"
                                                placeholder="30"
                                                value="{{ $persen !== null && $persen !== '' ? rtrim(rtrim(number_format((float) $persen, 1, '.', ''), '0'), '.') : '' }}"
                                                @disabled($belumModal)
                                            >
                                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">%</span>
                                        </div>
                                        <p class="form-hint mt-1">
                                            @if ($belumModal)
                                                Aktif setelah modal terisi
                                            @else
                                                Atau isi persen — harga ikut dihitung
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if (! $belumModal)
                                    <div class="module-pricing-card__profit" data-pricing-profit>
                                        Untung per {{ $product->unit }}:
                                        <strong data-pricing-amount class="{{ $item['untung'] >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $format::rupiah($item['untung'], 0) }}</strong>
                                        <span class="text-slate-600" data-pricing-percent>({{ $format::number($item['persen_untung']) }}%)</span>
                                    </div>
                                @else
                                    <p class="text-sm text-slate-500">Untung muncul otomatis setelah modal terisi.</p>
                                @endif

                                <div class="pricing-card-footer">
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

                <div class="module-empty hidden !py-8" data-pricing-search-empty>
                    <p class="module-empty__title">Tidak ada menu yang cocok</p>
                    <p class="module-empty__hint">Coba kata kunci lain.</p>
                </div>
            </div>

            <x-table-card :step="4" title="Rincian Modal" subtitle="Detail perhitungan bahan & biaya lain">
                <div class="p-4 sm:p-5">
                    <p class="text-sm text-slate-600">Lihat riwayat lengkap perhitungan modal per menu.</p>
                    <a href="{{ route('cogs.history') }}" class="btn-secondary btn-sm mt-3 inline-flex">Buka Rincian →</a>
                </div>
            </x-table-card>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const parseRupiah = (value) => {
        const string = String(value ?? '').trim();
        if (!string) return 0;
        if (/^\d+$/.test(string)) return parseInt(string, 10);
        const cleaned = string.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
        const parsed = parseFloat(cleaned);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const formatRupiah = (amount) => {
        const number = Math.max(0, Math.round(amount || 0));
        if (number <= 0) return '';
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(number);
    };

    const formatMoney = (amount) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount).replace(/\s/g, ' ');

    const clampPercent = (percent) => {
        if (!Number.isFinite(percent)) return NaN;
        return Math.min(99.9, Math.max(0, percent));
    };

    const priceFromPercent = (modal, percent) => {
        const p = clampPercent(percent) / 100;
        if (modal <= 0 || !Number.isFinite(p) || p >= 1) return 0;
        return Math.round(modal / (1 - p));
    };

    const percentFromPrice = (modal, selling) => {
        if (selling <= 0) return NaN;
        return ((selling - modal) / selling) * 100;
    };

    const setPrice = (form, amount) => {
        const visible = form.querySelector('.rupiah-input');
        const hidden = form.querySelector('input[data-rupiah-target="selling_price"]');
        const value = Math.max(0, Math.round(amount || 0));
        if (visible) visible.value = formatRupiah(value);
        if (hidden) hidden.value = value > 0 ? String(value) : '';
    };

    const setPercent = (form, percent) => {
        const input = form.querySelector('[data-pricing-margin]');
        if (!input || input.disabled) return;
        if (!Number.isFinite(percent)) {
            input.value = '';
            return;
        }
        input.value = String(Math.round(percent * 10) / 10);
    };

    const setMode = (form, mode) => {
        const input = form.querySelector('[data-pricing-mode]');
        if (input) input.value = mode;
    };

    const updateProfit = (form) => {
        const modal = parseFloat(form.dataset.modal || '0');
        const box = form.querySelector('[data-pricing-profit]');
        if (!box || modal <= 0) return;

        const selling = parseRupiah(form.querySelector('.rupiah-input')?.value ?? '');
        const untung = selling - modal;
        const persen = percentFromPrice(modal, selling);
        const amountEl = box.querySelector('[data-pricing-amount]');
        const percentEl = box.querySelector('[data-pricing-percent]');

        if (amountEl) {
            amountEl.textContent = formatMoney(untung);
            amountEl.classList.toggle('text-green-700', untung >= 0);
            amountEl.classList.toggle('text-red-600', untung < 0);
        }

        if (percentEl) {
            percentEl.textContent = Number.isFinite(persen)
                ? `(${persen.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%)`
                : '(—%)';
        }
    };

    const fromPrice = (form) => {
        if (form.dataset.pricingSyncing === '1') return;
        form.dataset.pricingSyncing = '1';
        try {
            const modal = parseFloat(form.dataset.modal || '0');
            const selling = parseRupiah(form.querySelector('.rupiah-input')?.value ?? '');
            setMode(form, 'price');
            if (modal > 0) setPercent(form, percentFromPrice(modal, selling));
            updateProfit(form);
        } finally {
            form.dataset.pricingSyncing = '0';
        }
    };

    const fromPercent = (form) => {
        if (form.dataset.pricingSyncing === '1') return;
        form.dataset.pricingSyncing = '1';
        try {
            const modal = parseFloat(form.dataset.modal || '0');
            const input = form.querySelector('[data-pricing-margin]');
            if (!input || input.disabled || modal <= 0) return;

            const raw = String(input.value || '').trim().replace(',', '.');
            setMode(form, 'percent');

            if (raw === '') {
                setPrice(form, 0);
                updateProfit(form);
                return;
            }

            const percent = clampPercent(parseFloat(raw));
            if (!Number.isFinite(percent)) return;

            setPrice(form, priceFromPercent(modal, percent));
            updateProfit(form);
        } finally {
            form.dataset.pricingSyncing = '0';
        }
    };

    document.querySelectorAll('[data-pricing-form]').forEach((form) => {
        const priceInput = form.querySelector('.rupiah-input');
        const percentInput = form.querySelector('[data-pricing-margin]');

        if (priceInput) {
            ['input', 'change', 'keyup', 'blur'].forEach((eventName) => {
                priceInput.addEventListener(eventName, () => fromPrice(form));
            });
        }

        if (percentInput) {
            ['input', 'change', 'keyup', 'blur'].forEach((eventName) => {
                percentInput.addEventListener(eventName, () => fromPercent(form));
            });
        }

        if (parseFloat(form.dataset.modal || '0') > 0) {
            fromPrice(form);
        } else {
            updateProfit(form);
        }
    });
})();
</script>
@endpush
