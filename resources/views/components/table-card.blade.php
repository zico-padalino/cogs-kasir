@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'table-card']) }}>
    @if ($title)
        <div class="table-card-header">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="table-scroll">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="table-card-footer">{{ $footer }}</div>
    @endisset
</div>
