@extends('layouts.app')

@section('title', 'Menu & Resep')
@section('heading', 'Langkah 3: Menu & Resep')
@section('subheading', 'Menu yang dijual — lalu tulis bahan yang dipakai')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500 sm:flex-1">Setelah tambah menu, buka <strong>Resep</strong> untuk isi bahannya.</p>
        <a href="{{ route('products.create') }}" class="btn-primary shrink-0">+ Tambah Menu</a>
    </div>

    <x-table-card title="Daftar Menu" subtitle="{{ $products->total() }} menu">
        @if ($products->isNotEmpty())
            <table class="table-default table-compact">
                <thead>
                    <tr>
                        <th>Nama menu</th>
                        <th>Resep</th>
                        <th>Modal</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                <p class="text-xs cell-muted">{{ $product->unit }}</p>
                            </td>
                            <td>
                                @if ($product->bill_of_materials_count > 0)
                                    <span class="badge badge-green">{{ $product->bill_of_materials_count }} bahan</span>
                                @else
                                    <span class="badge badge-amber">Belum ada resep</span>
                                @endif
                            </td>
                            <td class="cell-money">
                                @if ($product->unit_hpp > 0)
                                    {{ $format::rupiah($product->unit_hpp, 0) }}
                                @else
                                    <span class="text-slate-400">Belum dihitung</span>
                                @endif
                            </td>
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
                <p class="text-sm text-slate-500">Resep sudah lengkap? Catat produksi.</p>
                <a href="{{ route('production-orders.index') }}" class="btn-primary btn-sm">Ke Produksi →</a>
            </x-slot:footer>
        @else
            <div class="empty-state py-10">
                <p>Belum ada menu.</p>
                <p class="empty-hint">Tambahkan menu yang akan Anda jual, misalnya roti atau kue.</p>
                <a href="{{ route('products.create') }}" class="btn-primary btn-sm mt-4 inline-flex">+ Tambah Menu Pertama</a>
            </div>
        @endif
    </x-table-card>

    @if ($products->hasPages())
        <div class="pagination-wrap mt-3">{{ $products->links() }}</div>
    @endif
@endsection
