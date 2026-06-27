@props([
    'name',
    'value' => '',
    'label' => null,
    'placeholder' => '0',
    'required' => false,
    'decimals' => 0,
    'min' => 0,
    'class' => '',
])

@php
    $inputId = $attributes->get('id', $name);
    $oldValue = old($name, $value);
    $displayValue = $oldValue !== '' && $oldValue !== null
        ? \App\Support\Format::inputValue($oldValue, (int) $decimals)
        : '';
    $rawValue = $oldValue !== '' && $oldValue !== null
        ? \App\Support\Format::parseRupiah($oldValue)
        : '';
@endphp

<div {{ $attributes->class(['rupiah-field'])->only('class') }}>
    @if ($label)
        <label class="form-label" for="{{ $inputId }}">{{ $label }}</label>
    @endif
    <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
        <input
            type="text"
            id="{{ $inputId }}"
            inputmode="numeric"
            autocomplete="off"
            @class(['form-input pl-10 rupiah-input', $class])
            data-rupiah-hidden="{{ $name }}"
            data-rupiah-decimals="{{ (int) $decimals }}"
            data-rupiah-min="{{ $min }}"
            value="{{ $displayValue }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
        >
        <input type="hidden" name="{{ $name }}" value="{{ $rawValue !== '' ? $rawValue : '' }}" data-rupiah-target="{{ $name }}">
    </div>
</div>
