@extends('layouts.admin')

@section('title', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')
@section('heading', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
    <form method="POST" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="card max-w-2xl space-y-4" autocomplete="off">
        @csrf
        @if ($employee->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="employee_code">Kode</label>
                <input id="employee_code" name="employee_code" class="form-input" value="{{ old('employee_code', $employee->employee_code) }}" required>
            </div>
            <div>
                <label class="form-label" for="name">Nama</label>
                <input id="name" name="name" class="form-input" value="{{ old('name', $employee->name) }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="email">Email (opsional)</label>
                <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $employee->email) }}">
            </div>
            <div>
                <label class="form-label" for="hire_date">Tanggal masuk</label>
                <input id="hire_date" type="date" name="hire_date" class="form-input" value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}">
            </div>
            <div>
                <label class="form-label" for="base_salary">Gaji pokok</label>
                <input id="base_salary" type="number" min="0" step="1" name="base_salary" class="form-input" value="{{ old('base_salary', $employee->base_salary) }}" required>
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="active" @selected(old('status', $employee->status?->value ?? 'active') === 'active')>Aktif</option>
                    <option value="inactive" @selected(old('status', $employee->status?->value) === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="user_id">Akun login (opsional)</label>
                <select id="user_id" name="user_id" class="form-input">
                    <option value="">— Tidak perlu akun —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id', $employee->user_id) === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Hanya jika pegawai juga perlu login ke sistem. PIN kasir tetap wajib tanpa akun.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-4 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-brand-900">PIN Kasir <span class="text-red-600">*</span></h2>
                <p class="mt-1 text-xs text-brand-800/80">
                    @if ($hasPin ?? false)
                        PIN sudah aktif. Kosongkan jika tidak ingin mengganti, atau isi PIN baru di bawah.
                    @else
                        Wajib. PIN 4–6 digit dipakai membuka kasir (tanpa harus punya akun login).
                    @endif
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="pin">PIN (4–6 digit)</label>
                    <input
                        id="pin"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        name="pin"
                        class="form-input text-center text-lg tracking-[0.35em] font-bold"
                        maxlength="6"
                        minlength="4"
                        autocomplete="new-password"
                        value="{{ old('pin') }}"
                        @required(! ($hasPin ?? false))
                    >
                    @error('pin')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label" for="pin_confirmation">Ulangi PIN</label>
                    <input
                        id="pin_confirmation"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        name="pin_confirmation"
                        class="form-input text-center text-lg tracking-[0.35em] font-bold"
                        maxlength="6"
                        minlength="4"
                        autocomplete="new-password"
                        value="{{ old('pin_confirmation') }}"
                        @required(! ($hasPin ?? false))
                    >
                    @error('pin_confirmation')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div>
            <label class="form-label" for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="3" class="form-input">{{ old('notes', $employee->notes) }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('admin.employees.index') }}" class="btn-outline">Batal</a>
        </div>
    </form>

    @if ($employee->exists)
        <div class="card mt-6 max-w-2xl space-y-4" data-attendance-enroll>
            <div>
                <h2 class="text-base font-semibold text-slate-900">Daftarkan wajah</h2>
                <p class="mt-1 text-sm text-slate-500">Wajib agar karyawan bisa absen muka. Ambil foto jelas dari depan.</p>
            </div>

            @if ($employee->hasFaceEnrollment())
                <div class="flex items-center gap-3">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->face_photo_path) }}"
                        alt="Wajah terdaftar"
                        class="h-20 w-20 rounded-xl object-cover ring-1 ring-slate-200"
                    >
                    <p class="text-sm text-green-700">Wajah sudah terdaftar. Ambil ulang untuk mengganti.</p>
                </div>
            @else
                <p class="text-sm text-amber-700">Belum ada wajah terdaftar.</p>
            @endif

            <div class="attendance-camera-wrap face-cam">
                <video data-attendance-video class="attendance-video" playsinline muted autoplay></video>
                <canvas data-attendance-canvas class="hidden"></canvas>
                <div class="face-cam-flash" data-face-flash aria-hidden="true"></div>
                <p class="attendance-status face-cam-status" data-attendance-status>Menyiapkan kamera…</p>
            </div>

            <form action="{{ route('admin.employees.face', $employee) }}" method="POST" data-attendance-form>
                @csrf
                <input type="hidden" name="photo" data-attendance-photo>
                <input type="hidden" name="descriptor" data-attendance-descriptor>
                <button type="submit" class="btn-primary" data-attendance-submit disabled>Simpan wajah dari kamera</button>
            </form>
        </div>

        @vite(['resources/js/attendance-face.js'])
    @endif
@endsection
