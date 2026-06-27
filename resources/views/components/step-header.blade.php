@props(['number', 'title', 'description' => null])

@if (! ($setupFullyComplete ?? \App\Support\SetupProgress::isFullyComplete()))
<div class="mb-6 flex gap-4 rounded-xl border border-brand-100 bg-brand-50/50 p-4">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
        {{ $number }}
    </div>
    <div>
        <p class="font-semibold text-slate-900">Langkah {{ $number }}: {{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
    </div>
</div>
@endif
