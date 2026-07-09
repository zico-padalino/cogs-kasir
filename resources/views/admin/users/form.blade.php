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
        <div>
            <label class="form-label" for="password">Password {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}</label>
            <input id="password" type="password" name="password" class="form-input" {{ $user->exists ? '' : 'required' }}>
        </div>

        <div>
            <p class="form-label mb-2">Akses modul</p>
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
