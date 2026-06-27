@props(['step' => null])

@if (! ($setupFullyComplete ?? \App\Support\SetupProgress::isFullyComplete()))
@php
    $steps = $setupSteps ?? \App\Support\SetupProgress::steps();
    $current = $step ?? \App\Support\SetupProgress::currentStepNumber();
    $percent = \App\Support\SetupProgress::percentComplete();
@endphp

<div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Panduan Langkah demi Langkah</p>
            <p class="text-sm text-slate-500">{{ $percent }}% selesai · Langkah {{ $current }} dari 6</p>
        </div>
        <a href="{{ route('dashboard') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Lihat panduan →</a>
    </div>

    <div class="mb-3 h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $percent }}%"></div>
    </div>

    <div class="hidden gap-1 sm:grid sm:grid-cols-6">
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
