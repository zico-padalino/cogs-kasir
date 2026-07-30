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
    <label class="form-label" for="{{ $name }}">Satuan stok</label>

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        class="form-input"
        data-unit-preset
        required
    >
        @foreach ($presets as $value => $label)
            <option value="{{ $value }}" @selected($oldPreset === $value)>{{ $label }}</option>
        @endforeach
        <option value="other" @selected($oldPreset === 'other')>Lainnya…</option>
    </select>

    <div class="unit-picker__custom mt-2 {{ $showCustom ? '' : 'hidden' }}" data-unit-custom>
        <input
            type="text"
            id="{{ $customName }}"
            name="{{ $customName }}"
            class="form-input"
            placeholder="Tulis satuan…"
            value="{{ $oldCustom }}"
            maxlength="20"
            @disabled(! $showCustom)
        >
    </div>
</div>
