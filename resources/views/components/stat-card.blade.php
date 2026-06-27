@props(['label', 'value', 'color' => 'brand'])

@php
    $colors = [
        'brand' => 'bg-brand-50 text-brand-700 border-brand-100',
        'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
        'rose' => 'bg-rose-50 text-rose-700 border-rose-100',
        'slate' => 'bg-slate-50 text-slate-700 border-slate-200',
    ];
@endphp

<div class="card border {{ $colors[$color] ?? $colors['brand'] }}">
    <p class="text-xs font-medium uppercase tracking-wide opacity-80">{{ $label }}</p>
    <p class="mt-2 text-xl font-bold sm:text-2xl">{{ $value }}</p>
</div>
