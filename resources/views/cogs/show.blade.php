@extends('layouts.app')

@section('title', 'Detail Modal')
@section('heading', 'Rincian Modal')
@section('subheading', $calculation->product->name)

@section('content')
    <a href="{{ route('cogs.history') }}" class="cogs-detail-back">← Kembali ke riwayat</a>

    <article class="cogs-detail-card">
        <header class="cogs-detail-hero">
            <p class="cogs-detail-hero-label">Modal per porsi</p>
            <p class="cogs-detail-hero-value">{{ $format::rupiah($calculation->unit_cogs, 0) }}</p>
            <p class="cogs-detail-hero-sub">
                {{ $calculation->product->name }}
                · {{ $format::number($calculation->quantity, 0) }} {{ $calculation->product->unit }}
            </p>
        </header>

        <dl class="cogs-detail-breakdown">
            <div class="cogs-detail-line">
                <dt>Bahan Baku</dt>
                <dd>{{ $format::rupiah($calculation->direct_material) }}</dd>
            </div>
            <div class="cogs-detail-line">
                <dt>Upah kerja</dt>
                <dd>{{ $format::rupiah($calculation->direct_labor) }}</dd>
            </div>
            <div class="cogs-detail-line">
                <dt>Biaya lain</dt>
                <dd>{{ $format::rupiah($calculation->manufacturing_overhead) }}</dd>
            </div>
            <div class="cogs-detail-line is-total">
                <dt>Total modal</dt>
                <dd>{{ $format::rupiah($calculation->total_cogs) }}</dd>
            </div>
        </dl>

        <dl class="cogs-detail-meta">
            <div>
                <dt>Tanggal</dt>
                <dd>{{ $calculation->calculated_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt>Cara hitung</dt>
                <dd>{{ $calculation->calculation_method }}</dd>
            </div>
        </dl>
    </article>

    <div class="cogs-detail-actions">
        <a href="{{ route('cogs.calculate') }}" class="btn-primary btn-sm">Hitung lagi</a>
        <form action="{{ route('cogs.history.destroy', $calculation) }}" method="POST" onsubmit="return confirm('Hapus riwayat ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
        </form>
    </div>
@endsection
