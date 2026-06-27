@extends('layouts.app')

@section('title', $product->name)
@section('heading', $product->name)
@section('subheading', $product->sku . ' · ' . $product->type->label())

@section('content')
    <div class="page-toolbar">
        <a href="{{ route('products.index') }}" class="btn-ghost text-sm text-brand-600">← Kembali ke daftar</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('products.edit', $product) }}" class="btn-secondary btn-sm">Edit Produk</a>
            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-outline-danger btn-sm">Hapus Produk</button>
            </form>
        </div>
    </div>

    @if (in_array($product->type->value, ['finished_good', 'semi_finished']))
        <x-step-header number="3" title="Resep Produksi (BOM)"
            description="Tentukan bahan apa saja dan berapa banyak yang dibutuhkan untuk 1 {{ $product->unit }} {{ $product->name }}." />
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card">
            <p class="text-xs text-slate-500">Stok Tersedia</p>
            <p class="mt-1 text-xl font-bold">{{ $format::number($product->availableQuantity(), 2) }} {{ $product->unit }}</p>
        </div>
        <div class="card">
            <p class="text-xs text-slate-500">Jenis</p>
            <p class="mt-1 text-sm font-semibold">{{ $product->type->label() }}</p>
        </div>
        <div class="card">
            <p class="text-xs text-slate-500">Jumlah Bahan dalam Resep</p>
            <p class="mt-1 text-xl font-bold">{{ $product->billOfMaterials->count() }}</p>
        </div>
    </div>

    @if (in_array($product->type->value, ['finished_good', 'semi_finished']))
        <div class="card mb-6">
            <h2 class="mb-2 text-lg font-semibold">Resep / BOM</h2>
            <p class="mb-4 text-sm text-slate-500">Daftar bahan per 1 unit produk. Contoh: 0.5 kg adonan untuk 1 roti.</p>

            @if ($product->billOfMaterials->isNotEmpty())
                <div class="table-scroll mb-6 rounded-lg border border-slate-200">
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Jumlah</th>
                                <th>Sisa (scrap)</th>
                                <th class="col-actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product->billOfMaterials as $bom)
                                <tr>
                                    <td>
                                        <p class="font-semibold text-slate-900">{{ $bom->childProduct->name }}</p>
                                        <p class="text-xs cell-muted">{{ $bom->childProduct->sku }}</p>
                                    </td>
                                    <td>{{ $format::number($bom->quantity, 4) }} {{ $bom->childProduct->unit }}</td>
                                    <td>{{ $format::number($bom->scrap_percentage, 1) }}%</td>
                                    <td class="col-actions">
                                        <details class="inline-edit text-left">
                                            <summary>Edit</summary>
                                            <form action="{{ route('products.bom.update', [$product, $bom]) }}" method="POST" class="inline-edit-panel">
                                                @csrf @method('PUT')
                                                <input type="number" name="quantity" class="form-input text-xs" step="0.0001" min="0.0001" value="{{ $bom->quantity }}" required>
                                                <input type="number" name="scrap_percentage" class="form-input text-xs" step="0.1" min="0" value="{{ $bom->scrap_percentage }}">
                                                <button type="submit" class="btn-primary btn-sm w-full">Simpan</button>
                                            </form>
                                            <form action="{{ route('products.bom.destroy', [$product, $bom]) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus bahan dari resep?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus Bahan</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="alert-tip mb-4">Belum ada resep. Tambahkan bahan di bawah.</p>
            @endif

            <form action="{{ route('products.bom.store', $product) }}" method="POST" class="space-y-4 border-t border-slate-100 pt-6">
                @csrf
                <h3 class="text-sm font-semibold">+ Tambah Bahan ke Resep</h3>
                <div>
                    <label class="form-label">Pilih Bahan</label>
                    <select name="child_product_id" class="form-input" required>
                        <option value="">Pilih...</option>
                        @foreach ($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="form-label">Jumlah per unit</label>
                        <input type="number" name="quantity" class="form-input" step="0.0001" min="0.0001" required placeholder="0.5">
                    </div>
                    <div>
                        <label class="form-label">Scrap % (opsional)</label>
                        <input type="number" name="scrap_percentage" class="form-input" step="0.1" min="0" value="0">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Tambah ke Resep</button>
            </form>

            @if ($product->billOfMaterials->isNotEmpty())
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <a href="{{ route('inventory.index') }}" class="btn-primary">Lanjut ke Langkah 4: Stok Bahan →</a>
                </div>
            @endif
        </div>
    @endif

    @if ($product->type->value === 'raw_material')
        <div class="card">
            <h2 class="mb-4 text-lg font-semibold">Stok Bahan Ini</h2>
            @if ($product->inventoryLots->where('quantity_remaining', '>', 0)->isNotEmpty())
                <div class="table-scroll rounded-lg border border-slate-200">
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Lot</th>
                                <th>Sisa</th>
                                <th>Harga/Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product->inventoryLots->where('quantity_remaining', '>', 0) as $lot)
                                <tr>
                                    <td class="font-mono text-xs cell-muted">{{ $lot->lot_number ?? '-' }}</td>
                                    <td>{{ $format::number($lot->quantity_remaining, 2) }} {{ $product->unit }}</td>
                                    <td class="cell-money">{{ $format::rupiah($lot->unit_cost) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">Belum ada stok. <a href="{{ route('inventory.index') }}" class="text-brand-600">Terima stok di Langkah 4 →</a></p>
            @endif
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('products.index') }}" class="text-sm text-brand-600">← Kembali ke daftar produk</a>
    </div>
@endsection
