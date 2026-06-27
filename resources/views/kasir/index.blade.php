@extends('layouts.guest')

@section('title', 'Kasir')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 py-8" style="padding-bottom: max(2rem, env(safe-area-inset-bottom)); padding-top: max(1.5rem, env(safe-area-inset-top));">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-3xl">🧾</div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Modul Kasir</h1>
            <p class="mt-2 text-sm text-slate-600">
                Login berhasil. Fitur penjualan, struk, dan laporan kasir akan ditambahkan di tahap berikutnya.
            </p>

            @auth
                <p class="mt-4 text-sm text-slate-500">Masuk sebagai <strong>{{ auth()->user()->name }}</strong></p>
            @endauth

            <form action="{{ route('logout') }}" method="POST" class="mt-6">
                @csrf
                <button type="submit" class="btn-secondary">Keluar</button>
            </form>
        </div>
    </div>
@endsection
