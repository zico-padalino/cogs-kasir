@props(['edit' => null, 'delete' => null, 'show' => null, 'showLabel' => 'Lihat'])

<div {{ $attributes->merge(['class' => 'inline-flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto sm:gap-1.5']) }}>
    @if ($show)
        <a href="{{ $show }}" class="btn-sm btn-ghost text-brand-700 hover:bg-brand-50 hover:text-brand-800">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            {{ $showLabel }}
        </a>
    @endif
    @if ($edit)
        <a href="{{ $edit }}" class="btn-sm btn-outline">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
        </a>
    @endif
    @if ($delete)
        <form action="{{ $delete }}" method="POST" class="inline" onsubmit="return confirm('Yakin hapus data ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-sm btn-outline-danger">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus
            </button>
        </form>
    @endif
</div>
