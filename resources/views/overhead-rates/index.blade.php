@extends('layouts.app')

@section('title', 'Overhead')
@section('heading', 'Langkah 1: Biaya Overhead')
@section('subheading', 'Biaya tidak langsung — listrik, sewa, depresiasi mesin, dll')

@section('content')
    <x-step-header number="1" title="Biaya Overhead"
        description="Overhead = biaya yang tidak langsung terlihat di produk, tapi tetap dibutuhkan saat produksi. Contoh: 15% dari total biaya bahan baku." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card order-2 lg:order-1">
            <h2 class="mb-1 text-lg font-semibold">Tambah Tarif</h2>
            <p class="mb-4 text-xs text-slate-500">Isi nama, cara hitung, dan nilainya.</p>
            <form action="{{ route('overhead-rates.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-input" required placeholder="Contoh: Overhead Pabrik">
                </div>
                <div>
                    <label class="form-label">Dihitung dari</label>
                    <select name="allocation_base" class="form-input" required>
                        @foreach ($allocationBases as $base)
                            <option value="{{ $base->value }}">{{ $base->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Nilai</label>
                    <input type="number" name="rate" class="form-input" step="0.000001" min="0" required placeholder="0.15 atau 25000">
                    <p class="mt-1 text-xs text-slate-500">Pakai 0.15 untuk 15%, atau nominal seperti 25000</p>
                </div>
                <div>
                    <label class="form-label">Keterangan (opsional)</label>
                    <textarea name="description" class="form-input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn-primary w-full">Simpan Tarif</button>
            </form>

            <div class="info-box mt-6">
                <p class="font-medium text-slate-700">Contoh cepat</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                    <li>15% dari biaya bahan → basis: Bahan Langsung, nilai: 0.15</li>
                    <li>Rp 25.000 per jam kerja → basis: Jam Kerja, nilai: 25000</li>
                </ul>
            </div>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-2">
            <x-table-card title="Daftar Overhead" subtitle="{{ $rates->count() }} tarif aktif">
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
                                        {{ $rate->allocation_base->label() === 'Bahan Langsung' ? $format::number($rate->rate, 4) : $format::rupiah($rate->rate) }}
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
                        <p class="text-sm text-slate-500">Overhead siap? Lanjut buat daftar produk.</p>
                        <a href="{{ route('products.index') }}" class="btn-primary">Lanjut ke Produk →</a>
                    </x-slot:footer>
                @else
                    <div class="empty-state">
                        <p>Belum ada overhead.</p>
                        <p class="empty-hint">Tambahkan minimal 1 tarif di form kiri.</p>
                    </div>
                @endif
            </x-table-card>
        </div>
    </div>
@endsection
