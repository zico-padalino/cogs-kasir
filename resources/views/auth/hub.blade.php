@extends('layouts.guest')

@section('title', 'Pilih Modul')

@section('content')
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-10">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-slate-900">Halo, {{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Akun Anda punya lebih dari satu akses. Pilih modul yang ingin dibuka.</p>
        </div>

        @if (session('error'))
            <div class="auth-alert-error mb-4">{{ session('error') }}</div>
        @endif

        <div class="auth-module-grid max-w-none">
            @foreach ($modules as $module)
                <a href="{{ route('hub.switch', $module->value) }}" class="auth-module-pick block no-underline">
                    <span class="auth-module-pick-icon">{{ $module->icon() }}</span>
                    <span class="auth-module-pick-label text-slate-900">{{ $module->label() }}</span>
                    <span class="auth-module-pick-desc">{{ $module->description() }}</span>
                </a>
            @endforeach
        </div>

        <form action="{{ route('logout') }}" method="POST" class="mt-8 text-center">
            @csrf
            <button type="submit" class="btn-outline">Keluar</button>
        </form>
    </div>
@endsection
