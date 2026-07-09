@extends('layouts.app')

@section('title', $product->name)
@section('heading', $product->name)
@section('subheading', 'Resep bahan untuk 1 {{ $product->unit }}')

@section('content')
    <a href="{{ route('products.index') }}" class="cogs-detail-back">← Kembali ke menu</a>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="card p-3">
            <p class="text-xs text-slate-500">Bahan di resep</p>
            <p class="mt-1 text-lg font-bold">{{ $product->billOfMaterials->count() }}</p>
        </div>
        <div class="card p-3">
            <p class="text-xs text-slate-500">Stok siap jual</p>
            <p class="mt-1 text-lg font-bold">{{ $format::number($product->availableQuantity(), 0) }} {{ $product->unit }}</p>
        </div>
        <div class="card p-3 col-span-2 sm:col-span-1">
            <p class="text-xs text-slate-500">Modal / {{ $product->unit }}</p>
            <p class="mt-1 text-lg font-bold text-brand-700">
                {{ $product->unit_hpp > 0 ? $format::rupiah($product->unit_hpp, 0) : 'Belum dihitung' }}
            </p>
        </div>
    </div>

    <div class="card mb-4 p-4 sm:p-5">
        <h2 class="mb-1 text-sm font-semibold">Bahan Resep</h2>
        <p class="mb-4 text-xs text-slate-500">Berapa bahan dipakai untuk bikin 1 {{ $product->unit }} {{ $product->name }}.</p>

        @if ($allProducts->isEmpty())
            <p class="alert-tip mb-4">
                Belum ada bahan.
                <a href="{{ route('materials.index') }}" class="font-semibold text-brand-700">Tambah bahan dulu →</a>
            </p>
        @endif

        @if ($product->billOfMaterials->isNotEmpty())
            <div class="table-scroll mb-4 rounded-lg border border-slate-200">
                <table class="table-default table-compact">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Pakai</th>
                            <th class="col-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->billOfMaterials as $bom)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $bom->childProduct->name }}</td>
                                <td>{{ $format::number($bom->quantity, 4) }} {{ $bom->childProduct->unit }}</td>
                                <td class="col-actions">
                                    <details class="inline-edit text-left">
                                        <summary>Edit</summary>
                                        <form action="{{ route('products.bom.update', [$product, $bom]) }}" method="POST" class="inline-edit-panel">
                                            @csrf @method('PUT')
                                            <input type="number" name="quantity" class="form-input text-xs" step="0.0001" min="0.0001" value="{{ $bom->quantity }}" required>
                                            <button type="submit" class="btn-primary btn-sm w-full">Simpan</button>
                                        </form>
                                        <form action="{{ route('products.bom.destroy', [$product, $bom]) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus dari resep?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mb-4 text-sm text-amber-800 rounded-lg bg-amber-50 px-3 py-2">Resep masih kosong — tambahkan bahan di bawah.</p>
        @endif

        @if ($allProducts->isNotEmpty())
            <form action="{{ route('products.bom.store', $product) }}" method="POST" class="overhead-add-form border-t border-slate-100 pt-4">
                @csrf
                <div class="field-name sm:col-span-2">
                    <label class="form-label">Pilih bahan</label>
                    <select name="child_product_id" class="form-input" required>
                        <option value="">Pilih bahan...</option>
                        @foreach ($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} (stok {{ $format::number($p->availableQuantity(), 1) }} {{ $p->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-rate">
                    <label class="form-label">Jumlah dipakai</label>
                    <input type="number" name="quantity" class="form-input" step="0.0001" min="0.0001" required placeholder="0.5">
                </div>
                <div class="field-submit">
                    <button type="submit" class="btn-primary w-full">Tambah ke Resep</button>
                </div>
            </form>
        @endif

        @if ($product->billOfMaterials->isNotEmpty())
            <div class="mt-4 border-t border-slate-100 pt-4">
                <a href="{{ route('production-orders.index') }}" class="btn-primary btn-sm">Catat Produksi →</a>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('products.edit', $product) }}" class="btn-secondary btn-sm">Ubah nama menu</a>
        <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus menu ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
        </form>
    </div>
@endsection
