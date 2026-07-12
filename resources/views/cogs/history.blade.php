@extends('layouts.app')

@section('title', 'Rincian Modal')
@section('heading', 'Rincian Perhitungan Modal')
@section('subheading', 'Detail bahan, upah, dan biaya lain per produksi')

@section('content')
    <div class="mb-4">
        <a href="{{ route('menu-pricing.index') }}" class="cogs-detail-back">← Kembali ke Harga Jual</a>
    </div>

    <x-table-card class="cogs-history-card" title="Riwayat Produksi" :subtitle="null">
        @if ($calculations->isNotEmpty())
            <div class="cogs-history-summary">
                <span class="cogs-history-chip"><strong>{{ $format::number($summary->records, 0) }}</strong> catatan</span>
                <span class="cogs-history-chip">Total modal <strong>{{ $format::rupiah($summary->total_cost) }}</strong></span>
            </div>

            <div class="cogs-history-scroll">
                @foreach ($calculations as $calc)
                    <div class="cogs-history-row">
                        <a href="{{ route('cogs.history.show', $calc) }}" class="cogs-history-row-link">
                            <div class="cogs-history-row-main">
                                <p class="cogs-history-row-name">{{ $calc->product->name }}</p>
                                <p class="cogs-history-row-meta">
                                    {{ $calc->calculated_at->format('d/m · H:i') }}
                                    · {{ $format::number($calc->quantity, 0) }} {{ $calc->product->unit }}
                                </p>
                            </div>
                            <div class="cogs-history-row-values">
                                <span class="cogs-history-row-total">{{ $format::rupiah($calc->total_cogs) }}</span>
                                <span class="cogs-history-row-unit">{{ $format::rupiah($calc->unit_cogs, 0) }}/{{ $calc->product->unit }}</span>
                            </div>
                            <svg class="cogs-history-row-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <div class="cogs-history-row-delete">
                            <form action="{{ route('cogs.history.destroy', $calc) }}" method="POST" onsubmit="return confirm('Hapus riwayat ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-slot:footer>
                <div class="pagination-wrap w-full">{{ $calculations->links() }}</div>
            </x-slot:footer>
        @else
            <div class="cogs-history-empty">
                <p class="text-sm text-slate-600">Belum ada perhitungan modal.</p>
                <p class="mt-1 text-xs text-slate-500">Modal muncul setelah resep menu lengkap dan harga dihitung.</p>
                <a href="{{ route('products.index') }}" class="btn-primary btn-sm mt-4 inline-flex">Ke Menu & Resep</a>
            </div>
        @endif
    </x-table-card>
@endsection
