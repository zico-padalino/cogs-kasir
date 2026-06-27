@props(['number', 'title', 'description' => null])

@if (! ($setupFullyComplete ?? \App\Support\SetupProgress::isFullyComplete()))
<div class="step-header mb-4 sm:mb-6">
    <div class="step-header-badge">{{ $number }}</div>
    <div class="min-w-0 flex-1">
        <p class="step-header-title">Langkah {{ $number }}: {{ $title }}</p>
        @if ($description)
            <p class="step-header-desc">{{ $description }}</p>
        @endif
    </div>
</div>
@endif
