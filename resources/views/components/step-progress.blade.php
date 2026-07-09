@props(['step' => null])

@if (! ($setupFullyComplete ?? \App\Support\SetupProgress::isFullyComplete()))
@php
    $steps = $setupSteps ?? \App\Support\SetupProgress::steps();
    $totalSteps = \App\Support\SetupProgress::totalSteps();
    $current = $step ?? \App\Support\SetupProgress::currentStepNumber();
    $percent = \App\Support\SetupProgress::percentComplete();
@endphp

<div class="step-progress mb-4 sm:mb-6">
    <div class="step-progress-header">
        <div class="min-w-0 flex-1">
            <p class="step-progress-label">Langkah {{ $current }} dari {{ $totalSteps }}</p>
            <p class="step-progress-sub">{{ $percent }}% selesai</p>
        </div>
        <a href="{{ route('dashboard') }}" class="step-progress-link hidden sm:inline-flex">Panduan lengkap →</a>
    </div>

    <div class="step-progress-bar">
        <div class="step-progress-fill" style="width: {{ $percent }}%"></div>
    </div>

    <div class="step-grid-mobile sm:hidden">
        @foreach ($steps as $s)
            @php
                $isActive = $s['number'] === $current;
                $isDone = $s['done'];
            @endphp
            <a href="{{ route($s['route']) }}"
               class="step-pill {{ $isDone ? 'is-done' : ($isActive ? 'is-active' : '') }}"
               aria-current="{{ $isActive ? 'step' : 'false' }}">
                <span class="step-pill-num">{{ $isDone ? '✓' : $s['number'] }}</span>
                <span class="step-pill-text">{{ $s['short'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="hidden gap-1 sm:grid sm:grid-cols-5">
        @foreach ($steps as $s)
            @php
                $isActive = $s['number'] === $current;
                $isDone = $s['done'];
            @endphp
            <a href="{{ route($s['route']) }}"
               class="rounded-lg px-2 py-2 text-center text-xs transition {{ $isDone ? 'bg-green-50 text-green-700' : ($isActive ? 'bg-brand-50 text-brand-700 ring-1 ring-brand-200' : 'bg-slate-50 text-slate-500 hover:bg-slate-100') }}">
                <span class="block font-bold">{{ $s['number'] }}</span>
                <span class="mt-0.5 block truncate">{{ $s['short'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif
