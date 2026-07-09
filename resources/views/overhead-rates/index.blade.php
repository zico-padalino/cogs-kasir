@extends('layouts.app')

@section('title', 'Biaya Operasional')
@section('heading', 'Langkah 1: Biaya Operasional')
@section('subheading', 'Biaya tambahan seperti listrik, sewa, air, dan penyusutan mesin')

@section('content')
    <x-step-header number="1" title="Biaya Operasional"
        description="Ini biaya yang tidak langsung masuk ke satu produk, tapi tetap harus dibayar saat produksi. Contoh: listrik oven 15% dari total biaya bahan." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card order-2 lg:order-1">
            <h2 class="mb-1 text-lg font-semibold">Tambah Aturan Biaya</h2>
            <p class="mb-4 text-xs text-slate-500">Beri nama, pilih dasar perhitungan, lalu isi nilainya.</p>
            <form action="{{ route('overhead-rates.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" required placeholder="Contoh: Listrik & sewa dapur">
                </div>
                <div>
                    <label class="form-label">Dihitung dari apa?</label>
                    <select name="allocation_base" class="form-input" required>
                        @foreach ($allocationBases as $base)
                            <option value="{{ $base->value }}">{{ $base->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Nilai</label>
                    <input type="number" name="rate" class="form-input" step="0.000001" min="0" required placeholder="0.15 atau 25000">
                    <p class="mt-1 text-xs text-slate-500">Pakai 0,15 untuk 15%, atau angka rupiah seperti 25000</p>
                </div>
                <div>
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea name="description" class="form-input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn-primary w-full">Simpan</button>
            </form>

            <div class="info-box mt-6">
                <p class="font-medium text-slate-700">Contoh pengisian</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                    <li>15% dari biaya bahan → dasar: Total biaya bahan, nilai: 0,15</li>
                    <li>Rp 25.000 per jam kerja → dasar: Jam kerja, nilai: 25000</li>
                </ul>
            </div>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-2">
            <x-table-card title="Daftar Biaya Operasional" subtitle="{{ $rates->count() }} aturan aktif">
                @if ($rates->isNotEmpty())
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Dihitung dari</th>
                                <th>Nilai</th>
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
                                        {{ $rate->allocation_base->label() === 'Total biaya bahan' ? $format::number($rate->rate, 4) : $format::rupiah($rate->rate) }}
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
                        <p class="text-sm text-slate-500">Sudah cukup? Lanjut daftarkan produk.</p>
                        <a href="{{ route('products.index') }}" class="btn-primary">Lanjut ke Produk →</a>
                    </x-slot:footer>
                @else
                    <div class="empty-state">
                        <p>Belum ada biaya operasional.</p>
                        <p class="empty-hint">Tambahkan minimal 1 aturan di form sebelah kiri.</p>
                    </div>
                @endif
            </x-table-card>
        </div>
    </div>
@endsection
