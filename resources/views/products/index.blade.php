@extends('layouts.app')

@section('title', 'Produk')
@section('heading', 'Langkah 2: Daftar Produk')
@section('subheading', 'Catat bahan baku dan barang jadi yang akan dihitung biayanya')

@section('content')
    <x-step-header number="2" title="Daftar Produk"
        description="Buat 2 jenis: (1) Bahan Baku — tepung, gula, dll. (2) Barang Jadi — produk akhir yang dijual." />

    <div class="page-toolbar">
        <div class="alert-tip">
            💡 Langkah 3 (Resep) ada di halaman <strong>Detail</strong> barang jadi
        </div>
        <a href="{{ route('products.create') }}" class="btn-primary shrink-0">+ Tambah Produk</a>
    </div>

    <x-table-card title="Daftar Produk" subtitle="{{ $products->total() }} produk terdaftar">
        @if ($products->isNotEmpty())
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th>Resep</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td class="font-mono text-xs cell-muted">{{ $product->sku }}</td>
                            <td class="font-semibold text-slate-900">{{ $product->name }}</td>
                            <td>
                                <span class="badge badge-slate">{{ $product->type->label() }}</span>
                            </td>
                            <td class="cell-muted">{{ $product->bill_of_materials_count }} bahan</td>
                            <td class="col-actions">
                                <x-crud-actions
                                    :show="route('products.show', $product)"
                                    :edit="route('products.edit', $product)"
                                    :delete="route('products.destroy', $product)"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-slot:footer>
                <p class="text-sm text-slate-500">Sudah lengkap? Lanjut isi stok bahan baku.</p>
                <a href="{{ route('inventory.index') }}" class="btn-primary">Lanjut ke Stok →</a>
            </x-slot:footer>
        @else
            <div class="empty-state">
                <p>Belum ada produk.</p>
                <p class="empty-hint">Tambahkan bahan baku dan barang jadi untuk memulai.</p>
                <a href="{{ route('products.create') }}" class="btn-primary mt-5 inline-flex">+ Tambah Produk Pertama</a>
            </div>
        @endif
    </x-table-card>

    @if ($products->hasPages())
        <div class="pagination-wrap mt-4">{{ $products->links() }}</div>
    @endif
@endsection
