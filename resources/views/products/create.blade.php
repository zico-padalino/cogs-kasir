@extends('layouts.app')

@section('title', 'Tambah Menu')
@section('heading', 'Tambah Menu')
@section('subheading', 'Nama menu saja dulu — resep diisi setelah disimpan')

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="card p-4 sm:p-5">
            <form action="{{ route('products.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="form-label">Nama menu</label>
                    <input type="text" name="name" class="form-input" required placeholder="Roti tawar" value="{{ old('name') }}">
                </div>

                <x-menu-unit-select
                    :selected="old('unit_preset', 'pcs')"
                    :custom-value="old('unit_custom', '')"
                />

                <div class="form-actions pt-1">
                    <button type="submit" class="btn-primary">Simpan & Isi Resep</button>
                    <a href="{{ route('products.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
