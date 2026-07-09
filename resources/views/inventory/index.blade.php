@extends('layouts.app')

@section('title', 'Stok')
@section('heading', 'Langkah 4: Stok Bahan Baku')
@section('subheading', 'Catat bahan yang masuk gudang beserta harga belinya')

@section('content')
    <x-step-header number="4" title="Stok Bahan Baku"
        description="Tambah stok baru di form kiri. Ubah atau hapus per batch di tabel kanan." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card order-2 lg:order-1 lg:col-span-1">
            <h2 class="mb-1 text-lg font-semibold">+ Tambah Stok Masuk</h2>
            <p class="mb-4 text-xs text-slate-500">Isi saat menerima bahan dari supplier</p>
            <form action="{{ route('inventory.receive') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">Bahan</label>
                    <select name="product_id" class="form-input" required>
                        <option value="">Pilih bahan...</option>
                        @foreach ($rawMaterials as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Jumlah masuk</label>
                    <input type="number" name="quantity" class="form-input" step="0.01" min="0.01" required placeholder="500">
                </div>
                <div>
                    <x-rupiah-input name="unit_cost" label="Harga beli per satuan" placeholder="12.000" required />
                </div>
                <div>
                    <label class="form-label">No. batch (opsional)</label>
                    <input type="text" name="lot_number" class="form-input" placeholder="BATCH-001">
                </div>
                <button type="submit" class="btn-primary w-full">Simpan Stok</button>
            </form>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-2">
            <x-table-card title="Daftar Stok" subtitle="{{ $lots->count() }} batch tersedia">
                @if ($lots->isNotEmpty())
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Batch</th>
                                <th>Sisa / Masuk</th>
                                <th>Harga/satuan</th>
                                <th class="col-actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <td class="font-semibold text-slate-900">{{ $lot->product->name }}</td>
                                    <td class="font-mono text-xs cell-muted">{{ $lot->lot_number ?? '-' }}</td>
                                    <td>{{ $format::number($lot->quantity_remaining, 2) }} / {{ $format::number($lot->quantity_received, 2) }}</td>
                                    <td class="cell-money">{{ $format::rupiah($lot->unit_cost) }}</td>
                                    <td class="col-actions">
                                        <details class="inline-edit text-left">
                                            <summary>Edit</summary>
                                            <form action="{{ route('inventory.lots.update', $lot) }}" method="POST" class="inline-edit-panel">
                                                @csrf @method('PUT')
                                                <input type="text" name="lot_number" class="form-input text-xs" value="{{ $lot->lot_number }}" placeholder="No. batch">
                                                <input type="number" name="quantity_remaining" class="form-input text-xs" step="0.01" min="0" max="{{ $lot->quantity_received }}" value="{{ $lot->quantity_remaining }}">
                                                <x-rupiah-input name="unit_cost" :value="$lot->unit_cost" class="text-xs" />
                                                <button type="submit" class="btn-primary btn-sm w-full">Simpan</button>
                                            </form>
                                            @if ((float) $lot->quantity_remaining >= (float) $lot->quantity_received)
                                                <form action="{{ route('inventory.lots.destroy', $lot) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus batch stok ini?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus</button>
                                                </form>
                                            @endif
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <x-slot:footer>
                        <p class="text-sm text-slate-500">Stok sudah cukup? Lanjut ke produksi.</p>
                        <a href="{{ route('production-orders.index') }}" class="btn-primary">Lanjut ke Produksi →</a>
                    </x-slot:footer>
                @else
                    <div class="empty-state">
                        <p>Belum ada stok.</p>
                        <p class="empty-hint">Tambahkan stok bahan di form sebelah kiri.</p>
                    </div>
                @endif
            </x-table-card>
        </div>
    </div>
@endsection
