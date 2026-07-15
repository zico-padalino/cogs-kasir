@props([
    'stockUnitLabel' => null,
    'compact' => false,
    'optional' => false,
])

@php
    $mode = old('purchase_mode', 'direct');
    $packagePresets = [
        'botol' => 'botol',
        'kaleng' => 'kaleng',
        'jerigen' => 'jerigen',
        'dus' => 'dus',
        'karton' => 'karton',
        'sak' => 'sak',
        'pack' => 'pack',
        'box' => 'box',
        'bal' => 'bal',
    ];
    $packagePreset = old('package_preset', 'botol');
    $packageCustom = old('package_custom', '');
    $portionUnit = old('portion_unit', 'gr');
    $purchaseUnit = old('purchase_unit', 'kg');
    $require = ! $optional;
    $stockHint = $stockUnitLabel ?: 'satuan stok';
    $labelClass = $compact ? 'text-xs' : '';
    $inputClass = $compact ? 'text-sm' : '';
@endphp

<div
    {{ $attributes->merge(['class' => 'space-y-3']) }}
    data-material-purchase
    data-compact="{{ $compact ? '1' : '0' }}"
    data-optional="{{ $optional ? '1' : '0' }}"
    @if ($stockUnitLabel) data-stock-unit-label="{{ $stockUnitLabel }}" @endif
>
    <div>
        <p class="form-label mb-2">Cara beli</p>
        @if ($optional)
            <p class="mb-2 text-xs text-slate-500">Kosongkan jumlah jika hanya ubah nama/satuan.</p>
        @endif
        <div class="purchase-mode-grid grid gap-2 sm:grid-cols-3">
            <label class="module-choice">
                <input type="radio" name="purchase_mode" value="direct" class="mt-0.5" data-purchase-mode @checked($mode === 'direct')>
                <span class="min-w-0">
                    <strong class="block text-sm text-slate-900">Langsung</strong>
                    <span class="block text-[11px] leading-snug text-slate-500">Jumlah + harga total</span>
                </span>
            </label>
            <label class="module-choice">
                <input type="radio" name="purchase_mode" value="pack" class="mt-0.5" data-purchase-mode @checked($mode === 'pack')>
                <span class="min-w-0">
                    <strong class="block text-sm text-slate-900">Isi wadah</strong>
                    <span class="block text-[11px] leading-snug text-slate-500">Botol / dus → isi stok</span>
                </span>
            </label>
            <label class="module-choice">
                <input type="radio" name="purchase_mode" value="portion" class="mt-0.5" data-purchase-mode @checked($mode === 'portion')>
                <span class="min-w-0">
                    <strong class="block text-sm text-slate-900">Konversi</strong>
                    <span class="block text-[11px] leading-snug text-slate-500">Porsi ↔ kg / liter</span>
                </span>
            </label>
        </div>
    </div>

    <div class="space-y-3 {{ $mode === 'direct' ? '' : 'hidden' }}" data-purchase-direct>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="form-label {{ $labelClass }}">
                    Jumlah
                    <span class="font-normal text-slate-400">(<span data-pack-stock-unit-text>{{ $stockHint }}</span>)</span>
                </label>
                <input
                    type="number"
                    name="quantity"
                    class="form-input {{ $inputClass }}"
                    step="0.01"
                    min="0.01"
                    placeholder="25"
                    value="{{ old('quantity') }}"
                    data-direct-qty
                    @disabled($mode !== 'direct')
                    @required($require && $mode === 'direct')
                >
            </div>
            <div>
                <label class="form-label {{ $labelClass }}">Harga total</label>
                <x-rupiah-input name="direct_total" placeholder="300.000" :required="$require && $mode === 'direct'" />
            </div>
        </div>
    </div>

    <div class="space-y-3 {{ $mode === 'pack' ? '' : 'hidden' }}" data-purchase-pack>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="form-label {{ $labelClass }}">Jumlah wadah</label>
                <input
                    type="number"
                    name="package_qty"
                    class="form-input {{ $inputClass }}"
                    step="0.01"
                    min="0.01"
                    placeholder="1"
                    value="{{ old('package_qty') }}"
                    data-pack-qty
                    @disabled($mode !== 'pack')
                    @required($require && $mode === 'pack')
                >
            </div>
            <div>
                <label class="form-label {{ $labelClass }}">Jenis</label>
                <select name="package_preset" class="form-input {{ $inputClass }}" data-pack-preset @disabled($mode !== 'pack')>
                    @foreach ($packagePresets as $value => $label)
                        <option value="{{ $value }}" @selected($packagePreset === $value)>{{ $label }}</option>
                    @endforeach
                    <option value="other" @selected($packagePreset === 'other')>Lainnya…</option>
                </select>
                <div class="mt-2 {{ $packagePreset === 'other' ? '' : 'hidden' }}" data-pack-custom-wrap>
                    <input
                        type="text"
                        name="package_custom"
                        class="form-input {{ $inputClass }}"
                        placeholder="Nama wadah"
                        maxlength="20"
                        value="{{ $packageCustom }}"
                        data-pack-custom
                        @disabled($mode !== 'pack' || $packagePreset !== 'other')
                    >
                </div>
            </div>
            <div>
                <label class="form-label {{ $labelClass }}">
                    Isi per wadah
                    <span class="font-normal text-slate-400">(<span data-pack-stock-unit-text>{{ $stockHint }}</span>)</span>
                </label>
                <input
                    type="number"
                    name="units_per_package"
                    class="form-input {{ $inputClass }}"
                    step="0.01"
                    min="0.01"
                    placeholder="750"
                    value="{{ old('units_per_package') }}"
                    data-pack-units
                    @disabled($mode !== 'pack')
                    @required($require && $mode === 'pack')
                >
            </div>
            <div>
                <label class="form-label {{ $labelClass }}">Harga per wadah</label>
                <x-rupiah-input name="package_cost" placeholder="120.000" :required="$require && $mode === 'pack'" />
            </div>
        </div>
    </div>

    <div class="space-y-3 {{ $mode === 'portion' ? '' : 'hidden' }}" data-purchase-portion>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="form-label {{ $labelClass }}">1 stok =</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        name="portion_size"
                        class="form-input {{ $inputClass }} flex-1"
                        step="0.01"
                        min="0.01"
                        placeholder="250"
                        value="{{ old('portion_size', $optional ? '' : '250') }}"
                        data-portion-size
                        @disabled($mode !== 'portion')
                        @required($require && $mode === 'portion')
                    >
                    <select name="portion_unit" class="form-input max-w-[6.5rem] {{ $inputClass }}" data-portion-unit @disabled($mode !== 'portion')>
                        <option value="gr" @selected($portionUnit === 'gr')>gram</option>
                        <option value="kg" @selected($portionUnit === 'kg')>kg</option>
                        <option value="ml" @selected($portionUnit === 'ml')>ml</option>
                        <option value="liter" @selected($portionUnit === 'liter')>liter</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label {{ $labelClass }}">Jumlah dibeli</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        name="purchase_qty"
                        class="form-input {{ $inputClass }} flex-1"
                        step="0.01"
                        min="0.01"
                        placeholder="1"
                        value="{{ old('purchase_qty', $optional ? '' : '1') }}"
                        data-purchase-qty
                        @disabled($mode !== 'portion')
                        @required($require && $mode === 'portion')
                    >
                    <select name="purchase_unit" class="form-input max-w-[6.5rem] {{ $inputClass }}" data-purchase-unit @disabled($mode !== 'portion')>
                        <option value="kg" @selected($purchaseUnit === 'kg')>kg</option>
                        <option value="gr" @selected($purchaseUnit === 'gr')>gram</option>
                        <option value="liter" @selected($purchaseUnit === 'liter')>liter</option>
                        <option value="ml" @selected($purchaseUnit === 'ml')>ml</option>
                        <option value="pcs" @selected($purchaseUnit === 'pcs')>pcs</option>
                    </select>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label {{ $labelClass }}">Harga total</label>
                <x-rupiah-input name="purchase_cost" placeholder="80.000" :required="$require && $mode === 'portion'" />
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-900" data-purchase-preview>
        <p class="leading-relaxed" data-purchase-preview-text>
            @if ($optional)
                Isi angka untuk menambah stok, atau kosongkan jika hanya ubah data.
            @else
                Hasil hitung muncul di sini setelah angka diisi.
            @endif
        </p>
    </div>
</div>
