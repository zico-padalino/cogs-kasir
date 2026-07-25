@extends('layouts.kasir')

@section('title', 'Pengaturan Struk')
@section('heading', 'Pengaturan Struk')

@section('content')
    <div class="page-toolbar">
        <div>
            <h1 class="hidden text-2xl font-bold md:block">Pengaturan Struk</h1>
            <p class="text-sm text-slate-500">Atur ukuran kertas cetak pesanan &amp; dapur untuk semua kasir.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mx-auto max-w-lg">
        <h2 class="mb-1 text-lg font-semibold text-slate-900">Ukuran struk</h2>
        <p class="mb-5 text-sm text-slate-500">Pilihan ini dipakai saat Cetak Pesanan, Cetak Dapur, dan Thermal.</p>

        <form action="{{ route('kasir.settings.update') }}" method="POST" class="space-y-3">
            @csrf
            @method('PUT')

            @foreach ($options as $value => $meta)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition {{ $receiptPaper === $value ? 'border-brand-500 bg-brand-50' : 'border-slate-200 bg-white hover:border-brand-300' }}">
                    <input
                        type="radio"
                        name="receipt_paper"
                        value="{{ $value }}"
                        class="mt-1 accent-brand-600"
                        @checked($receiptPaper === $value)
                        required
                    >
                    <span class="min-w-0">
                        <span class="block font-semibold text-slate-900">{{ $meta['label'] }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">{{ $meta['hint'] }}</span>
                    </span>
                </label>
            @endforeach

            <button type="submit" class="btn-primary mt-4 w-full">Simpan Pengaturan</button>
        </form>
    </div>
@endsection
