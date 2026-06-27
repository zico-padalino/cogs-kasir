@extends('layouts.app')

@section('title', 'Reset Data')
@section('heading', 'Reset Semua Data')
@section('subheading', 'Hapus seluruh data — database kembali kosong total')

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="card mb-4">
            <h3 class="text-sm font-semibold text-slate-700">Data saat ini</h3>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                <div><dt class="text-slate-500">Produk</dt><dd class="font-bold">{{ $counts['products'] }}</dd></div>
                <div><dt class="text-slate-500">Overhead</dt><dd class="font-bold">{{ $counts['overhead_rates'] }}</dd></div>
                <div><dt class="text-slate-500">Stok (lot)</dt><dd class="font-bold">{{ $counts['inventory_lots'] }}</dd></div>
                <div><dt class="text-slate-500">Produksi</dt><dd class="font-bold">{{ $counts['production_orders'] }}</dd></div>
                <div><dt class="text-slate-500">Riwayat COGS</dt><dd class="font-bold">{{ $counts['cogs_calculations'] }}</dd></div>
            </dl>
        </div>

        <div class="card border-red-200 bg-red-50/50">
            <div class="mb-4 flex items-start gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <h2 class="font-semibold text-red-800">Hapus semua data</h2>
                    <p class="mt-1 text-sm text-red-700">
                        Semua data akan dihapus permanen. Tidak ada data demo yang diisi ulang.
                    </p>
                </div>
            </div>

            <form action="{{ route('reset-data.store') }}" method="POST" class="space-y-4 border-t border-red-200 pt-4">
                @csrf

                <div>
                    <label class="form-label">Ketik <strong>RESET</strong> untuk konfirmasi</label>
                    <input type="text" name="confirmation" class="form-input" placeholder="RESET" required autocomplete="off">
                </div>

                <div class="form-actions pt-2">
                    <button type="submit" class="btn-danger" onclick="return confirm('Yakin hapus SEMUA data?')">
                        Reset — Kosongkan Database
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
