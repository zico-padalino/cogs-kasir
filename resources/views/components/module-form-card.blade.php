@props([
    'step' => 1,
    'title',
    'description' => null,
    'icon' => null,
])

@php
    $icons = [1 => '⚡', 2 => '🥬', 3 => '🍽️', 4 => '👨‍🍳', 5 => '💰'];
    $displayIcon = $icon ?? ($icons[$step] ?? '📋');
@endphp

<div {{ $attributes->merge(['class' => "module-form-card module-step-{$step}"]) }}>
    <div class="module-form-card__header">
        <span class="module-step-badge" aria-hidden="true">{{ $step }}</span>
        <div class="min-w-0 flex-1">
            <h2 class="module-form-card__title">
                <span class="module-form-card__icon" aria-hidden="true">{{ $displayIcon }}</span>
                {{ $title }}
            </h2>
            @if ($description)
                <p class="module-form-card__desc">{{ $description }}</p>
            @endif
        </div>
    </div>
    <div class="module-form-card__body">
        {{ $slot }}
    </div>
</div>
