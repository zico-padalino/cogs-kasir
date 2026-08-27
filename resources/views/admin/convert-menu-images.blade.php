@extends('layouts.kasir')

@section('title', 'Konversi Gambar Menu')
@section('heading', 'Konversi Gambar Menu')
@section('subheading', 'Migrasi gambar upload lama ke format WebP')

@section('content')
    <div class="mx-auto max-w-2xl px-1">
        <div class="card p-5 sm:p-6">
            <h1 class="text-lg font-bold text-slate-900">Konversi gambar upload lama</h1>
            <p class="mt-2 text-sm text-slate-600">
                Semua gambar menu di folder <code>uploads/menu</code> akan dikonversi ke WebP.
                File lama dihapus setelah konversi berhasil.
            </p>

            @if (session('success'))
                <pre class="mt-4 overflow-x-auto rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800">{{ session('success') }}</pre>
            @endif
            @if (session('error'))
                <pre class="mt-4 overflow-x-auto rounded-lg bg-rose-50 p-3 text-xs text-rose-800">{{ session('error') }}</pre>
            @endif

            <form action="{{ route('admin.convert-menu-images.store') }}" method="POST" class="mt-5">
                @csrf
                <button type="submit" class="btn-primary" onclick="return confirm('Konversi semua gambar menu sekarang?')">
                    Konversi ke WebP
                </button>
            </form>
        </div>
    </div>
@endsection