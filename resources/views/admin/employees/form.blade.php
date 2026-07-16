@extends('layouts.admin')

@section('title', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')
@section('heading', $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
    <form method="POST" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="card max-w-2xl space-y-4">
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
            <div>
                <label class="form-label" for="phone">Telepon</label>
                <input id="phone" name="phone" class="form-input" value="{{ old('phone', $employee->phone) }}">
            </div>
            <div>
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-input" value="{{ old('email', $employee->email) }}">
            </div>
            <div>
                <label class="form-label" for="position">Jabatan</label>
                <input id="position" name="position" class="form-input" value="{{ old('position', $employee->position) }}">
            </div>
            <div>
                <label class="form-label" for="department">Departemen</label>
                <input id="department" name="department" class="form-input" value="{{ old('department', $employee->department) }}">
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
                    <option value="">— Tidak terhubung —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id', $employee->user_id) === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
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

            <div class="attendance-camera-wrap">
                <video data-attendance-video class="attendance-video" playsinline muted autoplay></video>
                <canvas data-attendance-canvas class="hidden"></canvas>
                <p class="attendance-status" data-attendance-status>Menyiapkan kamera…</p>
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
