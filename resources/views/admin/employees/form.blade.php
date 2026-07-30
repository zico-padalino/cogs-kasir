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
                <x-rupiah-input
                    name="base_salary"
                    label="Gaji pokok bulanan"
                    :value="old('base_salary', $employee->base_salary)"
                    placeholder="0"
                    required
                />
            </div>
            @if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'daily_salary'))
            <div>
                <x-rupiah-input
                    name="daily_salary"
                    label="Gaji harian"
                    :value="old('daily_salary', $employee->daily_salary ?? 0)"
                    placeholder="0"
                />
            </div>
            @endif
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
                <p class="mt-1 text-xs text-slate-500">Hanya jika pegawai juga perlu login ke sistem.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-4 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-brand-900">PIN Kasir (opsional)</h2>
                <p class="mt-1 text-xs text-brand-800/80">
                    @if ($hasPin ?? false)
                        PIN sudah aktif. Kosongkan jika tidak ingin mengganti, atau isi PIN baru di bawah.
                    @else
                        Opsional. PIN 4–6 digit dipakai membuka kasir (tanpa harus punya akun login).
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
                    >
                    @error('pin_confirmation')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Jadwal kerja (kalender mingguan)</h2>
                <p class="mt-1 text-xs text-slate-600">
                    Centang hari masuk, lalu pilih jam &amp; menit (format 24 jam, tanpa AM/PM).
                    Hari yang tidak dicentang = libur (tidak bisa absen).
                    @unless ($hasSchedules ?? false)
                        <span class="text-amber-700">Tabel jadwal belum ada di database — jalankan query <code>employee_work_schedules.sql</code> dulu.</span>
                    @endunless
                </p>
            </div>

            @if ($hasSchedules ?? false)
                @php
                    $hourOptions = range(0, 23);
                    $minuteOptions = range(0, 59);
                @endphp
                <div class="space-y-2">
                    @foreach ($dayLabels as $day => $label)
                        @php
                            $row = old("schedules.$day", $schedules[$day] ?? ['enabled' => false, 'clock_in' => '08:00', 'clock_out' => '17:00']);
                            $enabled = (bool) ($row['enabled'] ?? false);
                            $inParts = explode(':', (string) ($row['clock_in'] ?? '08:00'));
                            $outParts = explode(':', (string) ($row['clock_out'] ?? '17:00'));
                            $inH = (int) ($inParts[0] ?? 8);
                            $inM = (int) ($inParts[1] ?? 0);
                            $outH = (int) ($outParts[0] ?? 17);
                            $outM = (int) ($outParts[1] ?? 0);
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2" data-schedule-day>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                <input
                                    type="checkbox"
                                    name="schedules[{{ $day }}][enabled]"
                                    value="1"
                                    class="rounded border-slate-300 text-brand-600"
                                    data-schedule-enabled
                                    @checked($enabled)
                                >
                                {{ $label }}
                            </label>

                            <input type="hidden" name="schedules[{{ $day }}][clock_in]" value="{{ sprintf('%02d:%02d', $inH, $inM) }}" data-schedule-clock-in>
                            <input type="hidden" name="schedules[{{ $day }}][clock_out]" value="{{ sprintf('%02d:%02d', $outH, $outM) }}" data-schedule-clock-out>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="form-label">Masuk</p>
                                    <div class="flex items-center gap-1">
                                        <select class="form-input" data-schedule-in-h data-schedule-time aria-label="Jam masuk {{ $label }}">
                                            @foreach ($hourOptions as $h)
                                                <option value="{{ $h }}" @selected($inH === $h)>{{ sprintf('%02d', $h) }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-sm font-semibold text-slate-500">:</span>
                                        <select class="form-input" data-schedule-in-m data-schedule-time aria-label="Menit masuk {{ $label }}">
                                            @foreach ($minuteOptions as $m)
                                                <option value="{{ $m }}" @selected($inM === $m)>{{ sprintf('%02d', $m) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <p class="form-label">Pulang</p>
                                    <div class="flex items-center gap-1">
                                        <select class="form-input" data-schedule-out-h data-schedule-time aria-label="Jam pulang {{ $label }}">
                                            @foreach ($hourOptions as $h)
                                                <option value="{{ $h }}" @selected($outH === $h)>{{ sprintf('%02d', $h) }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-sm font-semibold text-slate-500">:</span>
                                        <select class="form-input" data-schedule-out-m data-schedule-time aria-label="Menit pulang {{ $label }}">
                                            @foreach ($minuteOptions as $m)
                                                <option value="{{ $m }}" @selected($outM === $m)>{{ sprintf('%02d', $m) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500">Format 24 jam (00–23). Contoh shift sore: masuk <strong>16:00</strong>, pulang <strong>23:59</strong>.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <h2 class="text-sm font-semibold text-slate-900">Cara absen</h2>
            <p class="mt-1 text-xs text-slate-600">
                Karyawan absen lewat QR dengan selfie dan GPS. Tidak perlu mendaftarkan wajah terlebih dahulu.
            </p>
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

    @if ($hasSchedules ?? false)
    <script>
        function pad2(n) {
            return String(n).padStart(2, '0');
        }

        document.querySelectorAll('[data-schedule-day]').forEach((row) => {
            const enabled = row.querySelector('[data-schedule-enabled]');
            const times = row.querySelectorAll('[data-schedule-time]');
            const clockIn = row.querySelector('[data-schedule-clock-in]');
            const clockOut = row.querySelector('[data-schedule-clock-out]');
            const inH = row.querySelector('[data-schedule-in-h]');
            const inM = row.querySelector('[data-schedule-in-m]');
            const outH = row.querySelector('[data-schedule-out-h]');
            const outM = row.querySelector('[data-schedule-out-m]');

            const syncHidden = () => {
                if (clockIn && inH && inM) {
                    clockIn.value = pad2(inH.value) + ':' + pad2(inM.value);
                }
                if (clockOut && outH && outM) {
                    clockOut.value = pad2(outH.value) + ':' + pad2(outM.value);
                }
            };

            const syncDisabled = () => {
                times.forEach((input) => {
                    input.disabled = !enabled.checked;
                    input.classList.toggle('bg-slate-100', !enabled.checked);
                });
            };

            [inH, inM, outH, outM].forEach((el) => el?.addEventListener('change', syncHidden));
            enabled?.addEventListener('change', syncDisabled);
            syncHidden();
            syncDisabled();
        });
    </script>
    @endif

@endsection
