@extends('layouts.attendance')

@section('title', 'Lengkapi Profil')

@section('content')
    @php
        $needFace = ! $employee->hasFaceEnrollment();
        $needPhone = ! filled(trim((string) $employee->phone));
    @endphp

    <div
        class="profile-setup-card"
        @if ($needFace) data-attendance-enroll data-face-guide="1" @endif
    >
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

        <ol class="profile-setup-steps" aria-label="Progress">
            <li class="{{ $needPhone ? 'is-current' : 'is-done' }}">
                <span class="profile-setup-step-num">1</span>
                <span>Telepon</span>
            </li>
            <li class="{{ $needFace ? ($needPhone ? '' : 'is-current') : 'is-done' }}">
                <span class="profile-setup-step-num">2</span>
                <span>Wajah</span>
            </li>
            <li>
                <span class="profile-setup-step-num">3</span>
                <span>Selesai</span>
            </li>
        </ol>

        <form
            action="{{ route('employee.profile.setup.update') }}"
            method="POST"
            class="profile-setup-form"
            @if ($needFace) data-attendance-form @endif
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

            @if ($needFace)
                <section class="profile-setup-section">
                    <div class="profile-setup-face-head">
                        <p class="form-label mb-0">Daftarkan wajah</p>
                        <p class="profile-setup-pose-count" data-face-pose-count>0 / 5</p>
                    </div>

                    <div class="attendance-camera-wrap profile-setup-camera">
                        <video data-attendance-video class="attendance-video" playsinline muted autoplay></video>
                        <canvas data-attendance-canvas class="hidden"></canvas>
                        <div class="attendance-camera-overlay" aria-hidden="true">
                            <div class="attendance-face-frame"></div>
                        </div>
                        <p class="attendance-guide" data-face-guide-text>Menyiapkan kamera…</p>
                        <p class="attendance-status" data-attendance-status>Mohon tunggu…</p>
                    </div>

                    <ul class="profile-setup-pose-list" data-face-pose-list>
                        <li data-pose="center">Depan</li>
                        <li data-pose="left">Kiri</li>
                        <li data-pose="right">Kanan</li>
                        <li data-pose="up">Atas</li>
                        <li data-pose="down">Bawah</li>
                    </ul>

                    <button type="button" class="btn-outline w-full" data-face-capture-pose disabled>
                        Ambil pose ini
                    </button>

                    <input type="hidden" name="photo" data-attendance-photo>
                    <input type="hidden" name="descriptor" data-attendance-descriptor>
                </section>

                <button type="submit" class="btn-primary w-full py-3" data-attendance-submit disabled>
                    Simpan & lanjut
                </button>
            @else
                <section class="profile-setup-section profile-setup-face-done">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->face_photo_path) }}"
                        alt="Wajah terdaftar"
                        class="profile-setup-face-thumb"
                    >
                    <p class="text-sm font-medium text-green-800">Wajah sudah terdaftar</p>
                </section>
                <button type="submit" class="btn-primary w-full py-3">Simpan & lanjut</button>
            @endif
        </form>

        <form action="{{ route('logout') }}" method="POST" class="profile-setup-logout">
            @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-slate-700">Keluar akun</button>
        </form>
    </div>
@endsection
