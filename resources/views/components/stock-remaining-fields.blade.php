@props([
    'stockUnit',
    'currentQty' => 0,
    'maxQty' => null,
    'compact' => false,
    'defaultQty' => null,
])

@php
    use App\Support\MaterialUnits;

    $stockUnit = MaterialUnits::normalize($stockUnit) ?: (string) $stockUnit;
    $unitOptions = MaterialUnits::recipeOptions($stockUnit);
    $preferredUnit = old('adjust_unit', $stockUnit);
    if (! array_key_exists($preferredUnit, $unitOptions)) {
        $preferredUnit = array_key_first($unitOptions) ?: $stockUnit;
    }
    $mode = old('adjust_mode', 'direct');
    $isCount = MaterialUnits::family($stockUnit) === 'count';
    $qtyDefault = $defaultQty ?? $currentQty;
    $showPortion = $isCount;
@endphp

<div
    {{ $attributes->merge(['class' => 'space-y-3']) }}
    data-stock-remaining
    data-stock-unit="{{ $stockUnit }}"
    @if ($maxQty !== null) data-max-qty="{{ $maxQty }}" @endif
>
    @if ($showPortion)
        <div>
            <p class="form-label {{ $compact ? 'text-xs' : '' }} mb-2">Cara isi stok sisa</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="module-choice">
                    <input type="radio" name="adjust_mode" value="direct" class="mt-1" data-adjust-mode @checked($mode === 'direct')>
                    <span class="text-sm">
                        <strong class="text-slate-900">Langsung</strong>
                        <span class="mt-0.5 block text-xs text-slate-500">Isi dalam {{ MaterialUnits::label($stockUnit) }}{{ count($unitOptions) > 1 ? ' / satuan sejenis' : '' }}.</span>
                    </span>
                </label>
                <label class="module-choice">
                    <input type="radio" name="adjust_mode" value="portion" class="mt-1" data-adjust-mode @checked($mode === 'portion')>
                    <span class="text-sm">
                        <strong class="text-slate-900">Dari berat/volume</strong>
                        <span class="mt-0.5 block text-xs text-slate-500">Sisa 500 gram, 1 stok = 250 gram → 2 stok.</span>
                    </span>
                </label>
            </div>
        </div>
    @else
        <input type="hidden" name="adjust_mode" value="direct" data-adjust-mode>
    @endif

    <div class="{{ $mode === 'portion' && $showPortion ? 'hidden' : '' }}" data-adjust-direct>
        <label class="form-label {{ $compact ? 'text-xs' : '' }}">Stok sisa aktual</label>
        <div class="flex gap-2">
            <input
                type="number"
                name="quantity_remaining"
                class="form-input {{ $compact ? 'text-sm' : 'text-lg font-semibold' }} flex-1"
                step="0.01"
                min="0"
                value="{{ old('quantity_remaining', \App\Support\Format::inputNumber($qtyDefault)) }}"
                placeholder="0"
                data-adjust-qty
                @required($mode !== 'portion' || ! $showPortion)
            >
            <select name="adjust_unit" class="form-input max-w-[7.5rem] {{ $compact ? 'text-sm' : '' }}" data-adjust-unit>
                @foreach ($unitOptions as $value => $label)
                    <option value="{{ $value }}" @selected($preferredUnit === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="form-hint">Isi sesuai hitungan fisik. Sistem menyesuaikan batch otomatis.</p>
    </div>

    @if ($showPortion)
        <div class="space-y-3 {{ $mode === 'portion' ? '' : 'hidden' }}" data-adjust-portion>
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">1 satuan stok =</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        name="adjust_portion_size"
                        class="form-input {{ $compact ? 'text-sm' : '' }} flex-1"
                        step="0.01"
                        min="0.01"
                        value="{{ old('adjust_portion_size', '250') }}"
                        data-adjust-portion-size
                        @disabled($mode !== 'portion')
                        @required($mode === 'portion')
                    >
                    <select name="adjust_portion_unit" class="form-input max-w-[7rem] {{ $compact ? 'text-sm' : '' }}" data-adjust-portion-unit @disabled($mode !== 'portion')>
                        <option value="gr" @selected(old('adjust_portion_unit', 'gr') === 'gr')>gram</option>
                        <option value="kg" @selected(old('adjust_portion_unit') === 'kg')>kg</option>
                        <option value="ml" @selected(old('adjust_portion_unit') === 'ml')>ml</option>
                        <option value="liter" @selected(old('adjust_portion_unit') === 'liter')>liter</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label {{ $compact ? 'text-xs' : '' }}">Sisa fisik dihitung</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        name="adjust_physical_qty"
                        class="form-input {{ $compact ? 'text-sm' : '' }} flex-1"
                        step="0.01"
                        min="0"
                        value="{{ old('adjust_physical_qty') }}"
                        placeholder="500"
                        data-adjust-physical-qty
                        @disabled($mode !== 'portion')
                        @required($mode === 'portion')
                    >
                    <select name="adjust_physical_unit" class="form-input max-w-[7rem] {{ $compact ? 'text-sm' : '' }}" data-adjust-physical-unit @disabled($mode !== 'portion')>
                        <option value="gr" @selected(old('adjust_physical_unit', 'gr') === 'gr')>gram</option>
                        <option value="kg" @selected(old('adjust_physical_unit') === 'kg')>kg</option>
                        <option value="ml" @selected(old('adjust_physical_unit') === 'ml')>ml</option>
                        <option value="liter" @selected(old('adjust_physical_unit') === 'liter')>liter</option>
                    </select>
                </div>
                <p class="form-hint">Contoh: sisa keju 500 gram, sementara 1 stok = 250 gram.</p>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-amber-200 bg-white/80 px-3 py-2 text-xs text-slate-700" data-adjust-preview>
        Akan disimpan sebagai stok {{ MaterialUnits::label($stockUnit) }}.
    </div>
</div>
