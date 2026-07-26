@extends('layouts.app')

@section('title', 'Dana Usaha')
@section('heading', 'Dana Usaha')
@section('subheading', 'Omzet bersih, pengeluaran, dan saldo usaha')

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="text-sm text-slate-500">Saldo dana usaha saat ini</p>
            <p class="text-xl font-bold text-slate-900">{{ $format::rupiah($balance) }}</p>
        </div>
    </div>

    <div class="card mb-4 border-brand-100 bg-brand-50/40">
        <p class="text-sm font-semibold text-slate-900">Cara membaca perputaran uang</p>
        <p class="mt-1 text-sm text-slate-600">
            Saldo awal + seluruh omzet bersih (tunai, QRIS, dan transfer) − pengeluaran = saldo akhir.
        </p>
        <p class="mt-1 text-xs text-slate-500">
            Ini adalah arus dana usaha, bukan laba. Modal/HPP tetap dihitung terpisah di laporan COGS.
        </p>
    </div>

    <form method="GET" action="{{ route('business-funds.index') }}" class="card mb-4 p-4">
        <label class="form-label" for="fund-date">Tanggal laporan</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <input
                id="fund-date"
                type="date"
                name="date"
                max="{{ now()->toDateString() }}"
                value="{{ $date->toDateString() }}"
                class="form-input"
                required
            >
            <button type="submit" class="btn-primary justify-center">Tampilkan</button>
            @if (! $date->isToday())
                <a href="{{ route('business-funds.index') }}" class="btn-outline justify-center">Hari ini</a>
            @endif
        </div>
    </form>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="1. Saldo awal" :value="$format::rupiah($opening)" color="slate" />
        <x-stat-card label="2. + Omzet bersih" :value="$format::rupiah($revenue)" color="green" />
        <x-stat-card label="3. − Pengeluaran" :value="$format::rupiah($expense)" color="rose" />
        <x-stat-card label="4. = Saldo akhir" :value="$format::rupiah($closing)" color="brand" />
    </div>

    <div class="card mb-4">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-semibold text-espresso">
                    {{ $editExpense ? 'Ubah pengeluaran' : 'Catat pengeluaran' }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">Pengeluaran ini hanya memengaruhi Dana Usaha, bukan Kas Tunai.</p>
            </div>
            @if ($editExpense)
                <a
                    href="{{ route('business-funds.index', ['date' => $date->toDateString()]) }}"
                    class="btn-outline"
                >
                    Batal ubah
                </a>
            @endif
        </div>

        <form
            method="POST"
            action="{{ $editExpense ? route('business-funds.update', $editExpense) : route('business-funds.store') }}"
            class="grid gap-4 md:grid-cols-2"
        >
            @csrf
            @if ($editExpense)
                @method('PUT')
            @endif

            <x-rupiah-input
                name="amount"
                label="Nominal"
                :value="$editExpense?->amount"
                :required="true"
                :min="1"
            />

            <div>
                <label class="form-label" for="expense-date">Tanggal</label>
                <input
                    id="expense-date"
                    type="date"
                    name="date"
                    max="{{ now()->toDateString() }}"
                    value="{{ old('date', $editExpense?->occurred_at?->toDateString() ?? $date->toDateString()) }}"
                    class="form-input"
                    required
                >
            </div>

            <div>
                <label class="form-label" for="expense-category">Kategori</label>
                <select id="expense-category" name="category" class="form-input" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $editExpense?->category) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="expense-payment">Dibayar dengan</label>
                <select id="expense-payment" name="payment_method" class="form-input" required>
                    <option value="">Pilih metode</option>
                    @foreach ($paymentMethods as $method)
                        <option
                            value="{{ $method->value }}"
                            @selected(old('payment_method', $editExpense?->payment_method?->value) === $method->value)
                        >
                            {{ $method->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="form-label" for="expense-note">Keterangan</label>
                <input
                    id="expense-note"
                    type="text"
                    name="note"
                    maxlength="255"
                    value="{{ old('note', $editExpense?->note) }}"
                    placeholder="Contoh: Bayar listrik bulan Juli"
                    class="form-input"
                    required
                >
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="btn-primary w-full justify-center">
                    {{ $editExpense ? 'Simpan perubahan' : 'Catat pengeluaran' }}
                </button>
            </div>
        </form>
    </div>

    <x-table-card title="Riwayat Pengeluaran" :subtitle="$entries->count().' catatan'">
        <table class="table-default">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Kategori</th>
                    <th>Metode</th>
                    <th>Keterangan</th>
                    <th>Dicatat oleh</th>
                    <th>Nominal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $entry->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td><span class="badge-amber">{{ $entry->categoryLabel() }}</span></td>
                        <td>{{ $entry->payment_method->label() }}</td>
                        <td class="text-sm text-slate-700">{{ $entry->note }}</td>
                        <td class="text-xs text-slate-500">{{ $entry->user?->name ?? 'Sistem' }}</td>
                        <td class="cell-money text-red-600">−{{ $format::rupiah($entry->amount) }}</td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('business-funds.index', ['date' => $date->toDateString(), 'edit' => $entry->id]) }}"
                                    class="btn-outline"
                                >
                                    Ubah
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('business-funds.destroy', $entry) }}"
                                    onsubmit="return confirm('Hapus pengeluaran ini?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-sm text-slate-500">
                            Belum ada pengeluaran pada tanggal ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-card>
@endsection
