@props([
    'title' => null,
    'subtitle' => null,
    'step' => null,
    'icon' => null,
])

@php
    $listIcons = [1 => '📋', 2 => '📦', 3 => '📒', 4 => '📊', 5 => '🏷️'];
    $listIcon = $icon ?? ($step ? ($listIcons[$step] ?? '📋') : null);
    $stepClass = $step ? "module-list-card module-step-{$step}" : '';
@endphp

<div {{ $attributes->merge(['class' => "table-card {$stepClass}"]) }}>
    @if ($title)
        <div class="table-card-header module-list-card__header">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                @if ($step)
                    <span class="module-step-badge module-step-badge--sm" aria-hidden="true">{{ $step }}</span>
                @endif
                <div class="min-w-0">
                    <h2 class="module-list-card__title">
                        @if ($listIcon)
                            <span class="module-list-card__icon" aria-hidden="true">{{ $listIcon }}</span>
                        @endif
                        {{ $title }}
                    </h2>
                    @if ($subtitle)
                        <p class="module-list-card__subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="table-scroll module-list-card__body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="table-card-footer module-list-card__footer">{{ $footer }}</div>
    @endisset
</div>
