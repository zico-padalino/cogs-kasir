@extends('layouts.attendance')

@section('title', 'Lengkapi Profil')

@section('content')
    <div class="profile-setup-card">
        <header class="profile-setup-head">
            <div class="profile-setup-mark" aria-hidden="true">{{ \App\Support\ShopSettings::initial() }}</div>
            <div class="min-w-0">
                <p class="profile-setup-eyebrow">Langkah awal</p>
                <h1 class="profile-setup-title">Lengkapi Profil</h1>
                <p class="profile-setup-sub">{{ $user->name }}</p>
            </div>
        </header>

        @if (session('error'))
            <div class="profile-setup-alert profile-setup-alert-warn">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="profile-setup-alert profile-setup-alert-error">{{ $errors->first() }}</div>
        @endif

        <ol class="profile-setup-steps profile-setup-steps-2" aria-label="Progress">
            <li class="is-current">
                <span class="profile-setup-step-num">1</span>
                <span>Telepon</span>
            </li>
            <li>
                <span class="profile-setup-step-num">2</span>
                <span>Selesai</span>
            </li>
        </ol>

        <form
            action="{{ route('employee.profile.setup.update') }}"
            method="POST"
            class="profile-setup-form"
        >
            @csrf
            @method('PUT')

            <section class="profile-setup-section">
                <label class="form-label" for="phone">Nomor telepon</label>
                <input
                    id="phone"
                    type="tel"
                    name="phone"
                    class="form-input profile-setup-input"
                    value="{{ old('phone', $employee->phone) }}"
                    required
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="08xxxxxxxxxx"
                >
                <p class="profile-setup-hint">Dipakai kasir untuk menghubungi jika perlu.</p>
            </section>

            <button type="submit" class="btn-primary w-full py-3">
                Simpan & lanjut
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST" class="profile-setup-logout">
            @csrf
            <button type="submit" class="text-xs text-slate-400 hover:text-slate-700">Keluar akun</button>
        </form>
    </div>
@endsection
