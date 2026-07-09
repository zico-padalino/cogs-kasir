@extends('layouts.app')

@section('title', 'Biaya Lain')
@section('heading', 'Langkah 1: Biaya Lain')
@section('subheading', 'Listrik, sewa, air — di luar bahan & upah kerja')

@section('content')
    <div class="space-y-4">
        <div class="card overhead-add-card">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-900">Tambah Biaya Baru</h2>
                <p class="mt-0.5 text-xs text-slate-500">Biaya selain bahan dan upah yang ikut masuk ke harga jual. Isi lalu simpan.</p>
            </div>

            <form action="{{ route('overhead-rates.store') }}" method="POST" class="overhead-add-form">
                @csrf
                <div class="field-name">
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" required placeholder="Listrik & sewa dapur">
                </div>
                <div class="field-base">
                    <label class="form-label">Cara hitungnya</label>
                    <select name="allocation_base" class="form-input" required>
                        @foreach ($allocationBases as $base)
                            <option value="{{ $base->value }}">{{ $base->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-rate">
                    <label class="form-label">Besarnya</label>
                    <input type="number" name="rate" class="form-input" step="0.000001" min="0" required placeholder="0.15">
                </div>
                <div class="field-note">
                    <label class="form-label">Keterangan <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <input type="text" name="description" class="form-input" placeholder="Misal: 15% dari harga bahan">
                </div>
                <div class="field-submit">
                    <button type="submit" class="btn-primary w-full lg:px-3">Simpan</button>
                </div>
            </form>

            <p class="overhead-add-hint">
                <strong class="text-slate-600">Contoh:</strong> 0,15 = 15% dari harga bahan · 25000 = Rp 25.000 per jam kerja
            </p>
        </div>

        <x-table-card title="Sudah Dicatat" subtitle="{{ $rates->count() }} biaya">
            @if ($rates->isNotEmpty())
                <table class="table-default table-compact">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Cara hitung</th>
                            <th>Besarnya</th>
                            <th class="col-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $rate->name }}</p>
                                    @if ($rate->description)
                                        <p class="mt-0.5 text-xs cell-muted">{{ $rate->description }}</p>
                                    @endif
                                </td>
                                <td>{{ $rate->allocation_base->label() }}</td>
                                <td class="cell-money font-mono">
                                    {{ $rate->allocation_base->isRatioBased() ? $format::number($rate->rate, 4) : $format::rupiah($rate->rate) }}
                                </td>
                                <td class="col-actions">
                                    <x-crud-actions
                                        :edit="route('overhead-rates.edit', $rate)"
                                        :delete="route('overhead-rates.destroy', $rate)"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-slot:footer>
                        <p class="text-sm text-slate-500">Sudah cukup? Lanjut isi daftar bahan.</p>
                        <a href="{{ route('materials.index') }}" class="btn-primary btn-sm">Lanjut ke Bahan →</a>
                </x-slot:footer>
            @else
                <div class="empty-state py-10">
                    <p>Belum ada biaya yang dicatat.</p>
                    <p class="empty-hint">Isi form di atas, lalu tekan Simpan.</p>
                </div>
            @endif
        </x-table-card>
    </div>
@endsection
