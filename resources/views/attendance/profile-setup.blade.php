@extends('layouts.attendance')

@section('title', 'Lengkapi Data Karyawan')

@section('content')
    @php
        $needFace = ! $employee->hasFaceEnrollment();
    @endphp
    <div
        class="login-card max-w-md"
        @if ($needFace) data-attendance-enroll @endif
    >
        <div class="login-brand">
            <div class="login-logo-fallback">{{ \App\Support\ShopSettings::initial() }}</div>
            <div class="login-brand-copy">
                <h1 class="login-shop-name">Lengkapi Profil</h1>
                <p class="login-shop-title">{{ $user->name }} · wajib sebelum absen & kasir</p>
            </div>
        </div>

        <div class="login-divider" aria-hidden="true"></div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="auth-alert-error mb-4" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
            <p>Isi data karyawan yang masih kosong@if ($needFace), lalu daftarkan wajah dari kamera@endif.</p>
            @if ($missing !== [])
                <p class="mt-1 font-semibold text-amber-700">Belum lengkap: {{ implode(', ', $missing) }}</p>
            @endif
        </div>

        <form action="{{ route('employee.profile.setup.update') }}" method="POST" class="space-y-3" @if ($needFace) data-attendance-form @endif>
            @csrf
            @method('PUT')

            <div>
                <label class="form-label" for="phone">Telepon</label>
                <input
                    id="phone"
                    type="text"
                    name="phone"
                    class="form-input"
                    value="{{ old('phone', $employee->phone) }}"
                    required
                    placeholder="08xxxxxxxxxx"
                >
            </div>

            <div>
                <label class="form-label" for="position">Jabatan</label>
                <input
                    id="position"
                    type="text"
                    name="position"
                    class="form-input"
                    value="{{ old('position', $employee->position) }}"
                    required
                    placeholder="Contoh: Kasir"
                >
            </div>

            <div>
                <label class="form-label" for="department">Departemen (opsional)</label>
                <input
                    id="department"
                    type="text"
                    name="department"
                    class="form-input"
                    value="{{ old('department', $employee->department) }}"
                    placeholder="Contoh: Operasional"
                >
            </div>

            @if ($needFace)
                <div>
                    <p class="form-label">Daftarkan wajah</p>
                    <div class="attendance-camera-wrap mt-1.5">
                        <video data-attendance-video class="attendance-video" playsinline muted autoplay></video>
                        <canvas data-attendance-canvas class="hidden"></canvas>
                        <p class="attendance-status" data-attendance-status>Menyiapkan kamera…</p>
                    </div>
                    <input type="hidden" name="photo" data-attendance-photo>
                    <input type="hidden" name="descriptor" data-attendance-descriptor>
                </div>

                <button type="submit" class="btn-primary w-full py-3" data-attendance-submit disabled>
                    Simpan data & wajah
                </button>
            @else
                <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 p-3">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->face_photo_path) }}"
                        alt="Wajah"
                        class="h-14 w-14 rounded-lg object-cover"
                    >
                    <p class="text-sm text-green-800">Wajah sudah terdaftar.</p>
                </div>
                <button type="submit" class="btn-primary w-full py-3">Simpan data karyawan</button>
            @endif
        </form>

        <div class="mt-4 text-center">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-slate-700">Keluar akun</button>
            </form>
        </div>
    </div>
@endsection
