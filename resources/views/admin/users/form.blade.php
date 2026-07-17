@extends('layouts.admin')

@section('title', $user->exists ? 'Edit Akun' : 'Akun Baru')
@section('heading', $user->exists ? 'Edit Akses Akun' : 'Buat Akun Baru')

@section('content')
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-2xl space-y-4">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div>
            <label class="form-label" for="name">Nama</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $user->name) }}" required>
        </div>
        <div>
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required>
        </div>
        @if ($user->exists)
            <div>
                <label class="form-label" for="password">Password (kosongkan jika tidak diubah)</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-input"
                    autocomplete="new-password"
                    data-password-strength="#password-strength"
                >
                <div id="password-strength" class="password-strength" aria-live="polite">
                    <div class="password-strength-head">
                        <span class="password-strength-caption">Kekuatan password</span>
                        <span class="password-strength-label" data-strength-label>Masukkan password baru</span>
                    </div>
                    <div class="password-strength-bars" aria-hidden="true">
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                    </div>
                    <ul class="password-strength-tips">
                        <li data-strength-tip="length">Minimal 8 karakter</li>
                        <li data-strength-tip="mixed">Huruf besar &amp; kecil</li>
                        <li data-strength-tip="number">Angka</li>
                        <li data-strength-tip="symbol">Simbol (!@#...)</li>
                    </ul>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Password awal otomatis: <strong>{{ config('pos.default_user_password', 'password') }}</strong>.
                Saat login pertama, user <strong>wajib mengganti password</strong> sebelum bisa memakai modul.
            </div>
        @endif

        @if (auth()->user()->isRoot())
            <div>
                <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3">
                    <input
                        type="checkbox"
                        name="is_root"
                        value="1"
                        class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        @checked(old('is_root', $user->is_root))
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Jadikan akun root</span>
                        <span class="block text-xs text-slate-600">
                            Root punya akses semua modul. Setelah login, akun ini diarahkan ke halaman pilih modul.
                        </span>
                    </span>
                </label>
            </div>
        @endif

        <div>
            <p class="form-label mb-2">Akses modul</p>
            <p class="mb-2 text-xs text-slate-500">Jika akun root, semua modul otomatis aktif.</p>
            <div class="space-y-2">
                @foreach ($allModules as $module)
                    <label class="flex items-start gap-3 rounded-2xl border border-violet-100 bg-violet-50/40 px-4 py-3">
                        <input
                            type="checkbox"
                            name="modules[]"
                            value="{{ $module->value }}"
                            class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            @checked(in_array($module->value, old('modules', $user->moduleValues()), true))
                        >
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ $module->icon() }} {{ $module->label() }}</span>
                            <span class="block text-xs text-slate-500">{{ $module->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn-outline">Batal</a>
        </div>
    </form>
@endsection
