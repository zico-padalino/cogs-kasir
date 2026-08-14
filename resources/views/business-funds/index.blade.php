@extends('layouts.app')

@section('title', 'Dana Usaha')
@section('heading', 'Dana Usaha')
@section('subheading', 'Omzet bersih, pengeluaran, dan saldo usaha')

@section('content')
    <div class="card mb-4 overflow-hidden border-brand-100 bg-brand-50/40">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Uang usaha yang tersedia sekarang</p>
                <p class="mt-1 text-3xl font-bold {{ $balance < 0 ? 'text-rose-700' : 'text-espresso' }}">{{ $format::rupiah($balance) }}</p>
                <p class="mt-1 text-sm text-slate-500">Gabungan omzet bersih dikurangi seluruh pengeluaran yang sudah dicatat.</p>
            </div>
            <div class="rounded-xl border border-brand-100 bg-white/80 px-4 py-3 text-sm text-slate-600">
                <p class="font-semibold text-slate-800">Rumus sederhana</p>
                <p class="mt-1">Uang sebelumnya + penjualan − pengeluaran</p>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="mb-3">
            <h2 class="text-sm font-semibold text-slate-900">Lihat laporan tanggal</h2>
            <p class="mt-0.5 text-xs text-slate-500">Pilih tanggal untuk melihat uang masuk dan keluar pada hari tersebut.</p>
        </div>
        <form method="GET" action="{{ route('business-funds.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <input
                id="fund-date"
                aria-label="Tanggal laporan"
                type="date"
                name="date"
                max="{{ now()->toDateString() }}"
                value="{{ $date->toDateString() }}"
                class="form-input"
                required
            >
            <button type="submit" class="btn-primary justify-center">Lihat laporan</button>
            @if (! $date->isToday())
                <a href="{{ route('business-funds.index') }}" class="btn-outline justify-center">Kembali ke hari ini</a>
            @endif
        </form>
    </div>

    <div class="card mb-4">
        <div class="mb-4">
            <h2 class="font-display text-base font-semibold text-espresso">
                Perputaran Uang · {{ $date->isToday() ? 'Hari ini' : $date->translatedFormat('d M Y') }}
            </h2>
            <p class="mt-1 text-xs text-slate-500">Baca dari kiri ke kanan.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uang sebelumnya</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $format::rupiah($opening) }}</p>
                <p class="mt-1 text-xs text-slate-500">Sisa uang sampai kemarin</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Ditambah penjualan</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">+ {{ $format::rupiah($revenue) }}</p>
                <p class="mt-1 text-xs text-emerald-700">Omzet bersih semua pembayaran</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Dikurangi pengeluaran</p>
                <p class="mt-2 text-xl font-bold text-rose-700">− {{ $format::rupiah($expense) }}</p>
                <p class="mt-1 text-xs text-rose-700">Uang usaha yang dipakai</p>
            </div>
            <div class="rounded-2xl border {{ $closing < 0 ? 'border-rose-200 bg-rose-50' : 'border-brand-200 bg-brand-50' }} p-4">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $closing < 0 ? 'text-rose-700' : 'text-brand-700' }}">Uang tersisa</p>
                <p class="mt-2 text-xl font-bold {{ $closing < 0 ? 'text-rose-700' : 'text-espresso' }}">= {{ $format::rupiah($closing) }}</p>
                <p class="mt-1 text-xs {{ $closing < 0 ? 'text-rose-700' : 'text-brand-700' }}">
                    {{ $closing < 0 ? 'Minus, dibawa ke hari berikutnya' : 'Dibawa ke hari berikutnya' }}
                </p>
            </div>
        </div>
        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
            Catatan: saldo dana adalah arus uang, bukan laba. Modal/HPP dihitung terpisah pada laporan COGS.
        </p>
    </div>

    <div class="card mb-4">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-semibold text-espresso">
                    {{ $editExpense ? 'Ubah uang keluar' : 'Catat uang keluar' }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    Isi setiap kali uang usaha dipakai, termasuk jika uang tersisa jadi minus. Saldo akhir menyesuaikan dari total sisa. Pencatatan ini tidak mengubah saldo Kas Tunai.
                </p>
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
                label="Berapa uang yang keluar?"
                :value="$editExpense?->amount"
                :required="true"
                :min="1"
            />

            <div>
                <label class="form-label" for="expense-date">Kapan uang dikeluarkan?</label>
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
                <label class="form-label" for="expense-category">Uang dipakai untuk kategori apa?</label>
                <select id="expense-category" name="category" class="form-input" required>
                    <option value="">Pilih jenis pengeluaran</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $editExpense?->category) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="expense-payment">Uang dibayar lewat apa?</label>
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
                <label class="form-label" for="expense-note">Pengeluaran untuk apa?</label>
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
                    {{ $editExpense ? 'Simpan perubahan' : 'Simpan uang keluar' }}
                </button>
            </div>
        </form>
    </div>

    <x-table-card title="Daftar Uang Keluar" :subtitle="$entries->count().' catatan pada tanggal ini'">
        <table class="table-default">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Kategori</th>
                    <th>Metode</th>
                    <th>Keterangan</th>
                    <th>Dicatat oleh</th>
                    <th>Uang keluar</th>
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
