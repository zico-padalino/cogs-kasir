@extends('layouts.kasir')

@section('title', 'Pembukuan')
@section('heading', 'Pembukuan')
@section('subheading', 'Ringkasan penjualan lunas per hari')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500">
            {{ $date->isToday() ? 'Hari ini' : $date->translatedFormat('d M Y') }}
            · {{ $count }} transaksi
        </p>
        <a
            href="{{ route('kasir.pembukuan.pdf', ['date' => $date->toDateString()]) }}"
            target="_blank"
            class="btn-outline btn-sm shrink-0"
        >
            Cetak PDF
        </a>
    </div>

    <form method="GET" action="{{ route('kasir.pembukuan.index') }}" class="card mb-4 p-4">
        <div>
            <label class="form-label" for="pembukuan-date">Tanggal</label>
            <input
                id="pembukuan-date"
                type="date"
                name="date"
                value="{{ $date->toDateString() }}"
                class="form-input w-full"
                required
            >
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn-primary w-full justify-center">
                Tampilkan
            </button>
            @if (! $date->isToday())
                <a
                    href="{{ route('kasir.pembukuan.index') }}"
                    class="btn-outline w-full justify-center"
                    style="margin-top: 0.75rem; display: inline-flex;"
                >
                    Hari ini
                </a>
            @endif
        </div>
    </form>

    <div class="pembukuan-stats mb-4">
        <x-stat-card label="Omzet" :value="$format::rupiah($omzet)" color="green" />
        <x-stat-card label="Transaksi" :value="$format::number($count, 0)" color="brand" />
        <x-stat-card label="Rata-rata" :value="$format::rupiah($average)" color="slate" />
    </div>

    <div class="card mb-4 p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Metode bayar</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($byPayment as $payment)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $payment['label'] }}</p>
                        <p class="text-xs text-slate-500">{{ $payment['count'] }} transaksi</p>
                    </div>
                    <p class="text-sm font-semibold text-slate-900">{{ $format::rupiah($payment['total']) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Pesanan lunas</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $date->translatedFormat('d M Y') }}</p>
        </div>

        @forelse ($orders as $order)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">{{ $format::rupiah($order->total) }}</p>
                    <p class="mt-0.5 font-mono text-xs text-slate-600">{{ $order->order_number }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $order->paid_at?->format('H:i') ?? '-' }}
                        · {{ $order->payment_method?->label() ?? '-' }}
                        · {{ $order->source->label() }}
                        @if ($order->table)
                            · {{ $order->table->label }}
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-col gap-1">
                    <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                </div>
            </div>
        @empty
            <div class="empty-state px-4 py-8">
                <p>Belum ada penjualan lunas.</p>
                <p class="empty-hint">Pilih tanggal lain atau selesaikan pembayaran di POS.</p>
            </div>
        @endforelse
    </div>
@endsection
