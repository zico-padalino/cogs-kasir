@extends('layouts.kasir')

@section('title', 'Atur Kategori')
@section('heading', 'Atur Kategori')
@section('subheading', 'Tambah atau hapus kategori yang dipakai di POS')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500">{{ $categories->count() }} kategori menu</p>
        <a href="{{ route('kasir.products.index') }}" class="btn-outline btn-sm shrink-0">← Ke Kelola Menu</a>
    </div>

    <div class="kasir-category-page">
        <section class="card kasir-category-create">
            <h2 class="kasir-category-section-title">Tambah kategori</h2>
            <p class="kasir-category-section-hint">Nama kategori akan muncul sebagai tab di POS dan Kelola Menu.</p>

            <form action="{{ route('kasir.menu-categories.store') }}" method="POST" class="kasir-category-create-form">
                @csrf
                <div class="kasir-category-add-row">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="kasir-category-name">Nama kategori</label>
                        <input
                            id="kasir-category-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            class="form-input"
                            placeholder="Contoh: Minuman Dingin"
                            maxlength="50"
                            required
                            autocomplete="off"
                            autofocus
                        >
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn-primary shrink-0">Tambah</button>
                </div>
            </form>
        </section>

        <section class="card kasir-category-manage">
            <div class="kasir-category-manage-head">
                <h2 class="kasir-category-section-title">Daftar kategori</h2>
                <p class="kasir-category-section-hint">Kategori yang masih dipakai menu tidak dapat dihapus.</p>
            </div>

            @forelse ($categories as $category)
                <div class="kasir-category-item">
                    <div class="kasir-category-item-main">
                        <span class="kasir-category-item-name">{{ $category->name }}</span>
                        <span class="kasir-category-item-meta">
                            {{ $category->product_count }} menu
                        </span>
                    </div>

                    @if ($category->product_count > 0)
                        <button type="button" class="btn-outline btn-sm" disabled title="Masih dipakai {{ $category->product_count }} menu">
                            Terpakai
                        </button>
                    @else
                        <form
                            action="{{ route('kasir.menu-categories.destroy', $category) }}"
                            method="POST"
                            onsubmit="return confirm('Hapus kategori {{ $category->name }}?')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <p>Belum ada kategori.</p>
                    <p class="empty-hint">Tambahkan kategori baru di formulir di atas.</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection
