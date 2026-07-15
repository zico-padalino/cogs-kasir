@props([
    'name' => 'unit_preset',
    'customName' => 'unit_custom',
    'selected' => 'kg',
    'customValue' => '',
])

@php
    $presets = \App\Support\MaterialUnits::presets();
    $oldPreset = old($name, $selected);
    $oldCustom = old($customName, $customValue);
    $showCustom = $oldPreset === 'other';
@endphp

<div {{ $attributes->merge(['class' => 'unit-picker']) }} data-unit-picker>
    <p class="form-label mb-2">Satuan stok</p>

    <div class="unit-picker__grid" role="radiogroup" aria-label="Pilih satuan">
        @foreach ($presets as $value => $label)
            <label class="unit-picker__chip">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" class="sr-only" @checked($oldPreset === $value)>
                <span>{{ $label }}</span>
            </label>
        @endforeach
        <label class="unit-picker__chip">
            <input type="radio" name="{{ $name }}" value="other" class="sr-only" @checked($oldPreset === 'other')>
            <span>Lainnya…</span>
        </label>
    </div>

    <div class="unit-picker__custom mt-2 {{ $showCustom ? '' : 'hidden' }}" data-unit-custom>
        <input
            type="text"
            id="{{ $customName }}"
            name="{{ $customName }}"
            class="form-input max-w-xs"
            placeholder="Tulis satuan…"
            value="{{ $oldCustom }}"
            maxlength="20"
            @disabled(! $showCustom)
        >
    </div>
</div>
