@extends('layouts.admin')

@section('title', 'Pengaturan')
@section('heading', 'Pengaturan')
@section('subheading', 'Nama toko, logo, QR pembayaran, absensi, dan potongan gaji')

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
                <p class="mt-1 text-sm text-slate-500">PNG/JPG/WebP, maks. 2 MB. Disarankan kotak 512×512. Juga dipakai sebagai ikon tab browser.</p>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="Logo toko"
                            class="h-full w-full object-contain p-1.5"
                            data-logo-preview
                            data-original-src="{{ $logoUrl }}"
                        >
                    @else
                        <span class="text-2xl font-bold text-brand-600" data-logo-fallback>{{ \App\Support\ShopSettings::initial() }}</span>
                        <img
                            src=""
                            alt="Logo toko"
                            class="hidden h-full w-full object-contain p-1.5"
                            data-logo-preview
                            data-original-src=""
                        >
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="logo" class="btn-secondary btn-sm cursor-pointer">
                            Pilih logo
                        </label>
                        <span class="truncate text-xs text-slate-500" data-logo-filename>Belum ada file baru</span>
                    </div>
                    <input
                        type="file"
                        name="logo"
                        id="logo"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        data-logo-input
                    >
                    @error('logo')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    @if ($logoUrl)
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-brand-600" data-logo-remove>
                            Hapus logo saat ini
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="card space-y-4" data-qris-settings>
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-slate-900">QR pembayaran (QRIS)</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Muncul di kasir saat bayar QRIS. PNG/JPG/WebP, maks. 4 MB.
                    </p>
                </div>
                @if ($hasCustomQris)
                    <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-200">
                        QR kustom aktif
                    </span>
                @else
                    <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                        QR bawaan
                    </span>
                @endif
            </div>

            <div class="qris-settings-row">
                <div class="qris-settings-preview">
                    <div class="qris-settings-frame" style="width:5.75rem;height:5.75rem;max-width:100%;overflow:hidden">
                        <img
                            src="{{ $qrisUrl }}"
                            alt="QRIS pembayaran"
                            class="qris-settings-image"
                            style="width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;display:block"
                            data-qris-preview
                            data-original-src="{{ $qrisUrl }}"
                        >
                    </div>
                    <p class="qris-settings-caption">Preview</p>
                </div>

                <div class="qris-settings-actions min-w-0 flex-1">
                    <div>
                        <p class="text-sm font-medium text-slate-800">Ganti gambar QR</p>
                        <p class="mt-0.5 text-xs text-slate-500">Pilih file, lalu klik Simpan pengaturan di bawah.</p>
                    </div>

                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <label for="qris" class="btn-primary btn-sm cursor-pointer shrink-0">
                            Unggah QR baru
                        </label>
                        <span class="min-w-0 truncate text-xs text-slate-500" data-qris-filename>Belum ada file dipilih</span>
                    </div>
                    <input
                        type="file"
                        name="qris"
                        id="qris"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        data-qris-input
                    >
                    @error('qris')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    @if ($hasCustomQris)
                        <label class="flex items-start gap-2 rounded-xl bg-white px-3 py-2.5 text-sm text-slate-700 ring-1 ring-slate-200">
                            <input type="checkbox" name="remove_qris" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600" data-qris-remove>
                            <span>
                                <span class="font-medium text-slate-900">Kembalikan ke QR bawaan</span>
                                <span class="mt-0.5 block text-xs text-slate-500">Centang lalu simpan.</span>
                            </span>
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Absensi (jam & lokasi)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Atur jam masuk/pulang global (fallback). Jadwal pribadi tiap pegawai diatur di
                    <a href="{{ route('admin.employees.index') }}" class="font-medium text-brand-700 underline">Data Karyawan</a>.
                </p>
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
                <p class="mb-2 text-xs text-slate-500">
                    Centang dari Data Karyawan. Pegawai tanpa akun login tetap bisa dipilih dan muncul di scan QR.
                    Akun root tidak ditampilkan. Buat nama baru di menu Data Karyawan jika belum ada.
                </p>
                <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                    @forelse ($employees as $employee)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-100 hover:ring-brand-200">
                            <input
                                type="checkbox"
                                name="attendance_required_employee_ids[]"
                                value="{{ $employee->id }}"
                                class="mt-0.5 rounded border-slate-300 text-brand-600"
                                @checked(in_array((int) $employee->id, old('attendance_required_employee_ids', $requiredEmployeeIds), true))
                            >
                            <span class="min-w-0">
                                <span class="font-medium text-slate-900">{{ $employee->name }}</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $employee->employee_code }}
                                    @if ($employee->user)
                                        · {{ $employee->user->email }}
                                    @else
                                        · <span class="text-amber-700">Tanpa akun login</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="text-xs text-slate-500">
                            Belum ada Data Karyawan aktif.
                            <a href="{{ route('admin.employees.create') }}" class="font-medium text-brand-700 underline">Tambah pegawai</a>
                            dulu (boleh tanpa akun).
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="attendance_clock_in">Jam masuk</label>
                    <x-time-24-picker
                        name="attendance_clock_in"
                        id="attendance_clock_in"
                        :value="old('attendance_clock_in', $settings['attendance_clock_in'] ?? '08:00')"
                        :required="true"
                    />
                    <p class="mt-1 text-xs text-slate-500">Format 24 jam (contoh: 16:00).</p>
                </div>
                <div>
                    <label class="form-label" for="attendance_clock_out">Jam pulang</label>
                    <x-time-24-picker
                        name="attendance_clock_out"
                        id="attendance_clock_out"
                        :value="old('attendance_clock_out', $settings['attendance_clock_out'] ?? '17:00')"
                        :required="true"
                    />
                    <p class="mt-1 text-xs text-slate-500">Format 24 jam (contoh: 23:59).</p>
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

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Gaji karyawan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Semua potongan opsional (isi 0 atau kosongkan jika tidak dipakai).
                    Gaji pokok &amp; harian diambil dari Data Karyawan; potongan absensi dihitung otomatis dari data absensi.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-rupiah-input
                        name="salary_default_deduction"
                        label="Potongan rutin (opsional)"
                        :value="old('salary_default_deduction', $settings['salary_default_deduction'] ?? 0)"
                        placeholder="0"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">Kasbon, iuran, atau potongan tetap per bulan.</p>
                </div>
                <div>
                    <x-rupiah-input
                        name="salary_deduction_late"
                        label="Potongan telat datang / kali (opsional)"
                        :value="old('salary_deduction_late', $settings['salary_deduction_late'] ?? 0)"
                        placeholder="0"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">Dikalikan jumlah hari yang melewati toleransi di bawah.</p>
                </div>
                <div>
                    <label class="form-label" for="salary_late_after_minutes">Potongan telat berlaku setelah (menit)</label>
                    <input
                        type="number"
                        name="salary_late_after_minutes"
                        id="salary_late_after_minutes"
                        class="form-input"
                        min="0"
                        max="240"
                        value="{{ old('salary_late_after_minutes', $settings['salary_late_after_minutes'] ?? 0) }}"
                        placeholder="0"
                    >
                    <p class="mt-1.5 text-xs text-slate-500">
                        Contoh: isi <strong>15</strong> = potongan hanya jika check-in lebih dari 15 menit setelah jam masuk.
                        Isi <strong>0</strong> = potongan sejak melewati jam masuk.
                    </p>
                </div>
                <div>
                    <x-rupiah-input
                        name="salary_deduction_alpha"
                        label="Potongan tidak hadir / alpha / kali (opsional)"
                        :value="old('salary_deduction_alpha', $settings['salary_deduction_alpha'] ?? 0)"
                        placeholder="0"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">Dikalikan jumlah hari status Alpha.</p>
                </div>
                <div>
                    <x-rupiah-input
                        name="salary_deduction_izin"
                        label="Potongan izin / kali (opsional)"
                        :value="old('salary_deduction_izin', $settings['salary_deduction_izin'] ?? 0)"
                        placeholder="0"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">Dikalikan jumlah hari status Izin.</p>
                </div>
                <div>
                    <x-rupiah-input
                        name="salary_deduction_sakit"
                        label="Potongan sakit / kali (opsional)"
                        :value="old('salary_deduction_sakit', $settings['salary_deduction_sakit'] ?? 0)"
                        placeholder="0"
                    />
                    <p class="mt-1.5 text-xs text-slate-500">Dikalikan jumlah hari status Sakit.</p>
                </div>
            </div>
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

        function bindImagePicker(options) {
            var input = document.querySelector(options.input);
            var preview = document.querySelector(options.preview);
            var filename = document.querySelector(options.filename);
            var remove = options.remove ? document.querySelector(options.remove) : null;
            var fallback = options.fallback ? document.querySelector(options.fallback) : null;
            if (! input || ! preview) return;

            var objectUrl = null;

            function revoke() {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
            }

            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (! file) {
                    if (filename) filename.textContent = 'Belum ada file dipilih';
                    return;
                }
                if (remove) remove.checked = false;
                if (filename) filename.textContent = file.name;
                revoke();
                objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                preview.classList.remove('hidden');
                if (fallback) fallback.classList.add('hidden');
            });

            if (remove) {
                remove.addEventListener('change', function () {
                    if (! remove.checked) return;
                    input.value = '';
                    revoke();
                    var original = preview.getAttribute('data-original-src') || '';
                    if (original) {
                        preview.src = original;
                        preview.classList.remove('hidden');
                        if (fallback) fallback.classList.add('hidden');
                    } else {
                        preview.removeAttribute('src');
                        preview.classList.add('hidden');
                        if (fallback) fallback.classList.remove('hidden');
                    }
                    if (filename) filename.textContent = 'Belum ada file dipilih';
                });
            }
        }

        bindImagePicker({
            input: '[data-logo-input]',
            preview: '[data-logo-preview]',
            filename: '[data-logo-filename]',
            remove: '[data-logo-remove]',
            fallback: '[data-logo-fallback]',
        });

        bindImagePicker({
            input: '[data-qris-input]',
            preview: '[data-qris-preview]',
            filename: '[data-qris-filename]',
            remove: '[data-qris-remove]',
        });
    </script>
@endsection
