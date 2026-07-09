@props(['step' => 1, 'title' => null])

<div {{ $attributes->merge(['class' => "module-tip module-step-{$step}"]) }}>
    @if ($title)
        <p class="module-tip__title">{{ $title }}</p>
    @endif
    <div class="module-tip__body text-sm text-slate-700">
        {{ $slot }}
    </div>
</div>
