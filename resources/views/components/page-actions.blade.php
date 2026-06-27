@props(['back' => null, 'backLabel' => '← Kembali'])

<div {{ $attributes->merge(['class' => 'page-actions']) }}>
    @if ($back)
        <a href="{{ $back }}" class="page-back">{{ $backLabel }}</a>
    @endif
    @if ($slot->isNotEmpty())
        <div class="page-actions-group">
            {{ $slot }}
        </div>
    @endif
</div>
