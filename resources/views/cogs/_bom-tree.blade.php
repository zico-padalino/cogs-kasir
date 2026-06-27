@php $indent = $depth * 20; @endphp
<div style="margin-left: {{ $indent }}px" class="mb-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="font-medium">{{ $node['name'] }}</span>
            <span class="ml-2 font-mono text-xs text-slate-500">{{ $node['sku'] }}</span>
        </div>
        <div class="text-right">
            <span class="text-slate-500">{{ $format::number($node['quantity'], 4) }} × {{ $format::rupiah($node['unit_cost']) }}</span>
            <span class="ml-2 font-semibold text-brand-600">= {{ $format::rupiah($node['total_cost']) }}</span>
        </div>
    </div>
</div>
@foreach ($node['components'] ?? [] as $component)
    @include('cogs._bom-tree', ['node' => $component, 'format' => $format, 'depth' => $depth + 1])
@endforeach
