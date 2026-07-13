@props([
    'selected' => 'pcs',
    'customValue' => '',
])

@php
    $presets = \App\Support\MaterialUnits::menuPresets();
    $oldPreset = old('unit_preset', $selected);
    $oldCustom = old('unit_custom', $customValue);
    $showCustom = $oldPreset === 'other';
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }} data-menu-unit-select>
    <label class="form-label" for="unit_preset">Satuan</label>
    <select id="unit_preset" name="unit_preset" class="form-input" required data-menu-unit-preset>
        @foreach ($presets as $value => $label)
            <option value="{{ $value }}" @selected($oldPreset === $value)>{{ $label }}</option>
        @endforeach
        <option value="other" @selected($oldPreset === 'other')>Lainnya (tulis sendiri)</option>
    </select>

    <div class="{{ $showCustom ? '' : 'hidden' }}" data-menu-unit-custom>
        <label class="form-label" for="unit_custom">Tulis satuan sendiri</label>
        <input
            type="text"
            id="unit_custom"
            name="unit_custom"
            class="form-input"
            placeholder="Misal: bowl, set, tray"
            value="{{ $oldCustom }}"
            maxlength="20"
            @disabled(! $showCustom)
            @required($showCustom)
        >
    </div>

    <p class="form-hint">Pilih dari daftar, atau pilih <strong>Lainnya</strong> untuk isi manual.</p>
</div>
