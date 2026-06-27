@php
    $indentClass = match(true) {
        $depth === 0 => '',
        $depth === 1 => 'ml-3 sm:ml-5',
        default => 'ml-6 sm:ml-10',
    };
@endphp
<div class="{{ $indentClass }} mb-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5 text-sm">
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="min-w-0">
            <span class="font-medium">{{ $node['name'] }}</span>
            <span class="ml-0 mt-0.5 block font-mono text-xs text-slate-500 sm:ml-2 sm:mt-0 sm:inline">{{ $node['sku'] }}</span>
        </div>
        <div class="text-left sm:text-right">
            <span class="block text-xs text-slate-500 sm:inline sm:text-sm">{{ $format::number($node['quantity'], 4) }} × {{ $format::rupiah($node['unit_cost']) }}</span>
            <span class="mt-0.5 block font-semibold text-brand-600 sm:ml-2 sm:mt-0 sm:inline">= {{ $format::rupiah($node['total_cost']) }}</span>
        </div>
    </div>
</div>
@foreach ($node['components'] ?? [] as $component)
    @include('cogs._bom-tree', ['node' => $component, 'format' => $format, 'depth' => $depth + 1])
@endforeach
