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

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Absensi (jam & lokasi)</h2>
                <p class="mt-1 text-sm text-slate-500">Atur jam masuk/pulang dan titik koordinat toko untuk absen GPS.</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    name="attendance_enabled"
                    value="1"
                    class="rounded border-slate-300 text-brand-600"
                    @checked(old('attendance_enabled', $settings['attendance_enabled'] ?? '1') === '1')
                >
                Aktifkan absensi GPS
            </label>

            <div>
                <p class="form-label">Siapa yang wajib absen</p>
                <p class="mb-2 text-xs text-slate-500">Centang akun. Saat disimpan, akun masuk ke Data Karyawan otomatis. Login pertama wajib lengkapi nomor telepon.</p>
                <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                    @forelse ($users as $user)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-100 hover:ring-brand-200">
                            <input
                                type="checkbox"
                                name="attendance_required_user_ids[]"
                                value="{{ $user->id }}"
                                class="mt-0.5 rounded border-slate-300 text-brand-600"
                                @checked(in_array((int) $user->id, old('attendance_required_user_ids', $requiredUserIds), true))
                            >
                            <span>
                                <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-xs text-slate-500">Belum ada akun. Buat di Akses Akun dulu.</p>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="attendance_clock_in">Jam masuk</label>
                    <input
                        type="time"
                        name="attendance_clock_in"
                        id="attendance_clock_in"
                        class="form-input"
                        value="{{ old('attendance_clock_in', $settings['attendance_clock_in'] ?? '08:00') }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="attendance_clock_out">Jam pulang</label>
                    <input
                        type="time"
                        name="attendance_clock_out"
                        id="attendance_clock_out"
                        class="form-input"
                        value="{{ old('attendance_clock_out', $settings['attendance_clock_out'] ?? '17:00') }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="attendance_early_minutes">Boleh absen masuk lebih awal (menit)</label>
                    <input
                        type="number"
                        name="attendance_early_minutes"
                        id="attendance_early_minutes"
                        class="form-input"
                        min="0"
                        max="240"
                        value="{{ old('attendance_early_minutes', $settings['attendance_early_minutes'] ?? '60') }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="attendance_radius_meters">Radius lokasi (meter)</label>
                    <input
                        type="number"
                        name="attendance_radius_meters"
                        id="attendance_radius_meters"
                        class="form-input"
                        min="10"
                        max="5000"
                        step="1"
                        value="{{ old('attendance_radius_meters', $settings['attendance_radius_meters'] ?? '100') }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="attendance_latitude">Latitude toko</label>
                    <input
                        type="text"
                        inputmode="decimal"
                        name="attendance_latitude"
                        id="attendance_latitude"
                        class="form-input"
                        value="{{ old('attendance_latitude', $settings['attendance_latitude'] ?? '') }}"
                        placeholder="-6.200000"
                    >
                </div>
                <div>
                    <label class="form-label" for="attendance_longitude">Longitude toko</label>
                    <input
                        type="text"
                        inputmode="decimal"
                        name="attendance_longitude"
                        id="attendance_longitude"
                        class="form-input"
                        value="{{ old('attendance_longitude', $settings['attendance_longitude'] ?? '') }}"
                        placeholder="106.816666"
                    >
                </div>
            </div>
            <p class="text-xs text-slate-500">Salin koordinat dari Google Maps (klik kanan titik → koordinat). Contoh: -6.200000, 106.816666.</p>
            <button type="button" class="btn-outline btn-sm" data-attendance-fill-gps>
                Isi dari lokasi perangkat ini
            </button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary w-full sm:w-auto">Simpan pengaturan</button>
            <a href="{{ route('admin.dashboard') }}" class="btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>

    <script>
        document.querySelector('[data-attendance-fill-gps]')?.addEventListener('click', function () {
            if (! navigator.geolocation) {
                alert('GPS tidak tersedia di perangkat ini.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function (pos) {
                document.getElementById('attendance_latitude').value = pos.coords.latitude.toFixed(7);
                document.getElementById('attendance_longitude').value = pos.coords.longitude.toFixed(7);
            }, function () {
                alert('Gagal membaca lokasi. Izinkan akses lokasi di browser.');
            }, { enableHighAccuracy: true, timeout: 15000 });
        });
    </script>
@endsection
