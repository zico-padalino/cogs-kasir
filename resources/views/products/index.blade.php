@extends('layouts.app')

@section('title', 'Produk')
@section('heading', 'Langkah 2: Daftar Produk')
@section('subheading', 'Daftarkan bahan baku dan produk jadi yang akan dihitung biayanya')

@section('content')
    <x-step-header number="2" title="Daftar Produk"
        description="Ada 2 jenis: Bahan Baku (tepung, gula) dan Produk Jadi (roti, kue) yang siap dijual." />

    <div class="page-toolbar">
        <div class="alert-tip">
            💡 Resep bahan ada di halaman <strong>Detail</strong> produk jadi
        </div>
        <a href="{{ route('products.create') }}" class="btn-primary shrink-0">+ Tambah Produk</a>
    </div>

    <x-table-card title="Semua Produk" subtitle="{{ $products->total() }} produk terdaftar">
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
                <p class="text-sm text-slate-500">Sudah lengkap? Lanjut catat stok bahan.</p>
                <a href="{{ route('inventory.index') }}" class="btn-primary">Lanjut ke Stok →</a>
            </x-slot:footer>
        @else
            <div class="empty-state">
                <p>Belum ada produk.</p>
                <p class="empty-hint">Tambahkan bahan baku dan produk jadi untuk memulai.</p>
                <a href="{{ route('products.create') }}" class="btn-primary mt-5 inline-flex">+ Tambah Produk Pertama</a>
            </div>
        @endif
    </x-table-card>

    @if ($products->hasPages())
        <div class="pagination-wrap mt-4">{{ $products->links() }}</div>
    @endif
@endsection
