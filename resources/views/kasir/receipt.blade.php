@extends('layouts.kasir')

@section('title', 'Struk')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="card border-2 border-dashed border-slate-300 bg-white text-center" id="receipt">
            <p class="text-xs uppercase tracking-widest text-slate-500">Struk Pembayaran</p>
            <h1 class="mt-2 text-xl font-bold">COGS Sederhana</h1>
            <p class="mt-1 font-mono text-sm">{{ $order->order_number }}</p>
            <p class="text-xs text-slate-500">{{ $order->paid_at?->format('d/m/Y H:i') }}</p>

            @if ($order->table)
                <p class="mt-2 text-sm">Meja: <strong>{{ $order->table->label }}</strong></p>
            @endif

            <div class="my-6 border-t border-b border-slate-200 py-4 text-left text-sm">
                @foreach ($order->items as $item)
                    <div class="mb-2 flex justify-between gap-2">
                        <span>{{ $item->product->name }} × {{ $format::number($item->quantity, 0) }}</span>
                        <span class="shrink-0 font-medium">{{ $format::rupiah($item->line_total) }}</span>
                    </div>
                @endforeach
            </div>

            <p class="text-2xl font-bold text-brand-600">{{ $format::rupiah($order->total) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $order->payment_method?->label() }}</p>
            <p class="mt-4 text-xs text-slate-400">Stok & COGS tercatat otomatis</p>
        </div>

        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <button type="button" onclick="window.print()" class="btn-primary">Cetak</button>
            <a href="{{ route('kasir.index') }}" class="btn-secondary">Kasir Baru</a>
        </div>
    </div>

    <style>
        @media print {
            header, .btn-primary, .btn-secondary { display: none !important; }
            main { padding: 0 !important; }
            #receipt { border: none !important; box-shadow: none !important; }
        }
    </style>
@endsection
