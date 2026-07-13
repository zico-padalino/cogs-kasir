@props([
    'stockUnitLabel' => null,
    'compact' => false,
])

@php
    $mode = old('purchase_mode', 'direct');
    $packagePresets = [
        'dus' => 'dus',
        'karton' => 'karton',
        'sak' => 'sak',
        'pack' => 'pack',
        'box' => 'box',
        'bal' => 'bal',
    ];
    $packagePreset = old('package_preset', 'dus');
    $packageCustom = old('package_custom', '');
@endphp

<div
    {{ $attributes->merge(['class' => 'space-y-3']) }}
    data-material-purchase
    data-compact="{{ $compact ? '1' : '0' }}"
>
    <div>
        <p class="form-label mb-2">Cara beli</p>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="module-choice">
                <input type="radio" name="purchase_mode" value="direct" class="mt-1" data-purchase-mode @checked($mode === 'direct')>
                <span class="text-sm">
                    <strong class="text-slate-900">Langsung per satuan stok</strong>
                    <span class="mt-0.5 block text-xs text-slate-500">Contoh: beli 25 kg, harga per kg.</span>
                </span>
            </label>
            <label class="module-choice">
                <input type="radio" name="purchase_mode" value="pack" class="mt-1" data-purchase-mode @checked($mode === 'pack')>
                <span class="text-sm">
                    <strong class="text-slate-900">Beli per kemasan</strong>
                    <span class="mt-0.5 block text-xs text-slate-500">Contoh: 2 dus × 40 pcs, harga per dus.</span>
                </span>
            </label>
        </div>
    </div>

    <div class="space-y-3 {{ $mode === 'direct' ? '' : 'hidden' }}" data-purchase-direct>
        <div class="grid gap-4 {{ $compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2' }}">
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Jumlah masuk</label>
                <input
                    type="number"
                    name="quantity"
                    class="form-input {{ $compact ? 'text-sm' : 'text-lg font-semibold' }}"
                    step="0.01"
                    min="0.01"
                    placeholder="25"
                    value="{{ old('quantity') }}"
                    data-direct-qty
                    @disabled($mode !== 'direct')
                    @required($mode === 'direct')
                >
                <p class="form-hint">Dalam satuan stok{{ $stockUnitLabel ? ' ('.$stockUnitLabel.')' : '' }}.</p>
            </div>
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Harga per satuan stok</label>
                <x-rupiah-input name="unit_cost" placeholder="12.000" :required="$mode === 'direct'" />
                <p class="form-hint">Harga untuk 1 satuan stok.</p>
            </div>
        </div>
    </div>

    <div class="space-y-3 {{ $mode === 'pack' ? '' : 'hidden' }}" data-purchase-pack>
        <div class="grid gap-3 {{ $compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2' }}">
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Jumlah kemasan</label>
                <input
                    type="number"
                    name="package_qty"
                    class="form-input {{ $compact ? 'text-sm' : 'text-lg font-semibold' }}"
                    step="0.01"
                    min="0.01"
                    placeholder="2"
                    value="{{ old('package_qty') }}"
                    data-pack-qty
                    @disabled($mode !== 'pack')
                    @required($mode === 'pack')
                >
                <p class="form-hint">Misal: beli 2 dus.</p>
            </div>
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Nama kemasan</label>
                <select name="package_preset" class="form-input {{ $compact ? 'text-sm' : '' }}" data-pack-preset @disabled($mode !== 'pack')>
                    @foreach ($packagePresets as $value => $label)
                        <option value="{{ $value }}" @selected($packagePreset === $value)>{{ $label }}</option>
                    @endforeach
                    <option value="other" @selected($packagePreset === 'other')>Lainnya…</option>
                </select>
                <div class="mt-2 {{ $packagePreset === 'other' ? '' : 'hidden' }}" data-pack-custom-wrap>
                    <input
                        type="text"
                        name="package_custom"
                        class="form-input {{ $compact ? 'text-sm' : '' }}"
                        placeholder="Misal: karung"
                        maxlength="20"
                        value="{{ $packageCustom }}"
                        data-pack-custom
                        @disabled($mode !== 'pack' || $packagePreset !== 'other')
                    >
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Isi per kemasan</label>
                <input
                    type="number"
                    name="units_per_package"
                    class="form-input {{ $compact ? 'text-sm' : 'text-lg font-semibold' }}"
                    step="0.01"
                    min="0.01"
                    placeholder="40"
                    value="{{ old('units_per_package') }}"
                    data-pack-units
                    @disabled($mode !== 'pack')
                    @required($mode === 'pack')
                >
                <p class="form-hint">1 dus berisi berapa satuan stok? Misal 40 pcs.</p>
            </div>
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Harga per kemasan</label>
                <x-rupiah-input name="package_cost" placeholder="120.000" :required="$mode === 'pack'" />
                <p class="form-hint">Harga 1 dus / karton.</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-sm text-emerald-900" data-purchase-preview>
        <p class="font-semibold">Hasil hitung stok</p>
        <p class="mt-1 text-xs leading-relaxed" data-purchase-preview-text>
            Pilih cara beli dan isi angka — stok & harga per satuan dihitung otomatis.
        </p>
    </div>
</div>
