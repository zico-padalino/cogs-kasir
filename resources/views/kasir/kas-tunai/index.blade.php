@extends('layouts.kasir')

@section('title', 'Kas Tunai')
@section('heading', 'Kas Tunai')
@section('subheading', 'Saldo cash, setoran, kembalian, dan pengeluaran')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500">
            Saldo sekarang: <strong class="text-slate-900">{{ $format::rupiah($balance) }}</strong>
        </p>
    </div>

    <form method="GET" action="{{ route('kasir.kas-tunai.index') }}" class="card mb-4 p-4">
        <div>
            <label class="form-label" for="kas-date">Tanggal</label>
            <input
                id="kas-date"
                type="date"
                name="date"
                value="{{ $date->toDateString() }}"
                class="form-input w-full"
                required
            >
        </div>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn-primary w-full justify-center">Tampilkan</button>
            @if (! $date->isToday())
                <a
                    href="{{ route('kasir.kas-tunai.index') }}"
                    class="btn-outline w-full justify-center"
                    style="margin-top: 0.75rem; display: inline-flex;"
                >
                    Hari ini
                </a>
            @endif
        </div>
    </form>

                    <div class="pembukuan-stats mb-4">
        <x-stat-card label="Saldo awal" :value="$format::rupiah($opening)" color="slate" />
        <x-stat-card label="Setoran" :value="$format::rupiah($floatIn)" color="green" />
        <x-stat-card label="Penjualan tunai" :value="$format::rupiah($saleIn)" color="brand" />
        <x-stat-card label="Kembalian" :value="$format::rupiah($changeOut)" color="amber" />
        <x-stat-card label="Pengeluaran" :value="$format::rupiah($expense)" color="rose" />
        <x-stat-card label="Saldo akhir" :value="$format::rupiah($closing)" color="green" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2 mb-4">
        <form method="POST" action="{{ route('kasir.kas-tunai.float') }}" class="card p-4 space-y-3">
            @csrf
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Tambah uang cash</h2>
                <p class="mt-0.5 text-xs text-slate-500">Modal / setoran kas yang disediakan di laci.</p>
            </div>
            <div>
                <label class="form-label" for="float-amount">Nominal</label>
                <input id="float-amount" type="number" name="amount" min="1" step="1" class="form-input" required value="{{ old('amount') }}">
            </div>
            <div>
                <label class="form-label" for="float-note">Keterangan</label>
                <input id="float-note" type="text" name="note" class="form-input" maxlength="255" required placeholder="Contoh: Modal pagi" value="{{ old('note') }}">
            </div>
            <button type="submit" class="btn-primary w-full">Catat Setoran</button>
        </form>

        <form method="POST" action="{{ route('kasir.kas-tunai.expense') }}" class="card p-4 space-y-3">
            @csrf
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Pengeluaran cash</h2>
                <p class="mt-0.5 text-xs text-slate-500">Pembelian mendadak / pemakaian uang kas.</p>
            </div>
            <div>
                <label class="form-label" for="expense-amount">Nominal</label>
                <input id="expense-amount" type="number" name="amount" min="1" step="1" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="expense-note">Untuk apa</label>
                <input id="expense-note" type="text" name="note" class="form-input" maxlength="255" required placeholder="Contoh: Beli gula darurat">
            </div>
            <button type="submit" class="btn-secondary w-full">Catat Pengeluaran</button>
        </form>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Mutasi {{ $date->translatedFormat('d M Y') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">Termasuk kembalian otomatis dari penjualan tunai</p>
        </div>

        @forelse ($entries as $entry)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge {{ $entry->type->badgeClass() }}">{{ $entry->type->label() }}</span>
                        <span class="text-xs text-slate-500">{{ $entry->occurred_at?->format('H:i') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-800">{{ $entry->note }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $entry->user?->name ?? 'Sistem' }}
                        @if ($entry->order)
                            · <a href="{{ route('kasir.receipt', $entry->order) }}" class="text-brand-600 underline">{{ $entry->order->order_number }}</a>
                        @endif
                    </p>
                </div>
                <p @class([
                    'shrink-0 text-sm font-semibold',
                    'text-emerald-700' => $entry->direction === 'in',
                    'text-red-600' => $entry->direction === 'out',
                ])>
                    {{ $entry->direction === 'in' ? '+' : '-' }}{{ $format::rupiah($entry->amount) }}
                </p>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-slate-500">
                Belum ada mutasi kas di tanggal ini.
            </div>
        @endforelse
    </div>
@endsection
