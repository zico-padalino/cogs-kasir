@extends('layouts.app')

@section('title', 'Menu & Resep')
@section('heading', 'Langkah 3: Menu & Resep')
@section('subheading', 'Menu yang dijual — lalu tulis bahan yang dipakai')

@section('content')
    <div class="module-page module-step-3">
        <div class="module-toolbar">
            <p class="module-toolbar__text">Setelah tambah menu, buka <strong>Isi Resep</strong> untuk tulis bahannya.</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('materials.index') }}" class="btn-outline btn-sm shrink-0">← Bahan</a>
                <a href="{{ route('menu-pricing.index') }}" class="btn-outline btn-sm shrink-0">Harga Jual →</a>
                <a href="{{ route('products.create') }}" class="btn-primary shrink-0 py-2.5 font-semibold">+ Tambah Menu</a>
            </div>
        </div>

        <x-table-card :step="3" title="Daftar Menu" :subtitle="$products->total() . ' menu'">
            @if ($products->isNotEmpty())
                <table class="table-default">
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
                                    <p class="font-bold text-slate-900">{{ $product->name }}</p>
                                    <p class="text-xs cell-muted">per {{ $product->unit }}</p>
                                </td>
                                <td>
                                    @if ($product->bill_of_materials_count > 0)
                                        <span class="badge badge-green">{{ $product->bill_of_materials_count }} bahan</span>
                                    @else
                                        <span class="badge badge-amber">Belum ada resep</span>
                                    @endif
                                </td>
                                <td class="cell-highlight">
                                    @if ($product->unit_hpp > 0)
                                        {{ $format::rupiah($product->unit_hpp, 0) }}
                                    @else
                                        <span class="text-slate-400 font-normal">Belum dihitung</span>
                                    @endif
                                </td>
                                <td class="col-actions">
                                    <x-crud-actions
                                        :show="route('products.show', $product)"
                                        show-label="Isi Resep"
                                        :edit="route('products.edit', $product)"
                                        :delete="route('products.destroy', $product)"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-slot:footer>
                    <p class="text-sm font-medium text-slate-600">Resep sudah lengkap? Atur harga jual.</p>
                    <a href="{{ route('menu-pricing.index') }}" class="btn-primary btn-sm">Ke Harga Jual →</a>
                </x-slot:footer>
            @else
                <div class="module-empty">
                    <span class="module-empty__icon" aria-hidden="true">🍽️</span>
                    <p class="module-empty__title">Belum ada menu</p>
                    <p class="module-empty__hint">Tambahkan menu yang dijual — misalnya Nasi Goreng atau Kopi Susu.</p>
                    <a href="{{ route('products.create') }}" class="btn-primary btn-sm mt-4 inline-flex">+ Tambah Menu Pertama</a>
                </div>
            @endif
        </x-table-card>
    </div>

    @if ($products->hasPages())
        <div class="pagination-wrap mt-3">{{ $products->links() }}</div>
    @endif
@endsection
