@extends('layouts.admin')

@section('title', 'Pengaturan')
@section('heading', 'Pengaturan')
@section('subheading', 'Nama toko, judul, dan logo yang tampil di kasir, login, dan stiker QR')

@section('content')
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Identitas toko</h2>
                <p class="mt-1 text-sm text-slate-500">Perubahan langsung dipakai di seluruh aplikasi.</p>
            </div>

            <div>
                <label class="form-label" for="shop_name">Nama toko</label>
                <input
                    type="text"
                    name="shop_name"
                    id="shop_name"
                    class="form-input"
                    value="{{ old('shop_name', $settings['shop_name']) }}"
                    required
                    maxlength="80"
                    placeholder="Coffee & Kitchen"
                >
            </div>

            <div>
                <label class="form-label" for="shop_title">Judul / tagline</label>
                <input
                    type="text"
                    name="shop_title"
                    id="shop_title"
                    class="form-input"
                    value="{{ old('shop_title', $settings['shop_title']) }}"
                    maxlength="120"
                    placeholder="Menu & pesanan dari HP"
                >
                <p class="mt-1.5 text-xs text-slate-500">Muncul di halaman pesan, stiker QR, dan beberapa header.</p>
            </div>
        </div>

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Logo</h2>
                <p class="mt-1 text-sm text-slate-500">PNG/JPG/WebP, maks. 2 MB. Disarankan kotak 512×512. Logo ini juga dipakai sebagai ikon tab browser.</p>
            </div>

            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo toko" class="h-full w-full object-contain p-1.5">
                    @else
                        <span class="text-2xl font-bold text-brand-600">{{ \App\Support\ShopSettings::initial() }}</span>
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-3">
                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp" class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700">
                    @if ($logoUrl)
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-brand-600">
                            Hapus logo saat ini
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary w-full sm:w-auto">Simpan pengaturan</button>
            <a href="{{ route('admin.dashboard') }}" class="btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
@endsection
