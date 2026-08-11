<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\QrisDynamicService;
use App\Support\Format;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(AttendanceService $attendanceService, QrisDynamicService $qrisDynamic): View
    {
        $settings = ShopSettings::all();
        $payload = ShopSettings::qrisPayload();
        $payloadCheck = $payload !== '' ? $qrisDynamic->validate($payload) : null;

        return view('admin.settings.edit', [
            'settings' => $settings,
            'logoUrl' => ShopSettings::logoUrl(),
            'qrisUrl' => ShopSettings::qrisUrl(),
            'hasCustomQris' => ShopSettings::hasCustomQris(),
            'hasQrisPayload' => ShopSettings::hasQrisPayload(),
            'qrisPayload' => $payload,
            'qrisPayloadValid' => $payloadCheck['valid'] ?? null,
            'qrisPayloadSummary' => $payloadCheck['summary'] ?? null,
            'employees' => Employee::query()
                ->with('user:id,name,email,is_root')
                ->forAttendance()
                ->orderBy('name')
                ->get(),
            'requiredEmployeeIds' => $attendanceService->requiredEmployeeIds(),
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService, QrisDynamicService $qrisDynamic): RedirectResponse
    {
        $request->merge([
            'attendance_latitude' => $request->filled('attendance_latitude')
                ? $request->input('attendance_latitude')
                : null,
            'attendance_longitude' => $request->filled('attendance_longitude')
                ? $request->input('attendance_longitude')
                : null,
            // type=time kadang kirim HH:mm:ss — simpan sebagai HH:mm
            'attendance_clock_in' => $this->normalizeClockInput($request->input('attendance_clock_in')),
            'attendance_clock_out' => $this->normalizeClockInput($request->input('attendance_clock_out')),
            'qris_payload' => preg_replace('/[\r\n\t]+/', '', trim((string) $request->input('qris_payload', ''))) ?? '',
        ]);

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:80'],
            'shop_title' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'qris' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_qris' => ['sometimes', 'boolean'],
            'qris_payload' => ['nullable', 'string', 'max:2000'],
            'attendance_enabled' => ['sometimes', 'boolean'],
            'attendance_clock_in' => ['required', 'date_format:H:i'],
            'attendance_clock_out' => ['required', 'date_format:H:i'],
            'attendance_early_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_radius_meters' => ['required', 'numeric', 'min:10', 'max:5000'],
            'attendance_required_employee_ids' => ['nullable', 'array'],
            'attendance_required_employee_ids.*' => ['integer', 'exists:employees,id'],
            'salary_default_deduction' => ['nullable', 'numeric', 'min:0'],
            'salary_deduction_late' => ['nullable', 'numeric', 'min:0'],
            'salary_late_after_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'salary_deduction_alpha' => ['nullable', 'numeric', 'min:0'],
            'salary_deduction_izin' => ['nullable', 'numeric', 'min:0'],
            'salary_deduction_sakit' => ['nullable', 'numeric', 'min:0'],
        ], [
            'shop_name.required' => 'Nama toko wajib diisi.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'qris.mimes' => 'QR pembayaran harus berupa gambar JPG/PNG/WebP.',
            'qris.max' => 'Ukuran QR pembayaran maksimal 4 MB.',
            'attendance_clock_in.date_format' => 'Format jam masuk tidak valid.',
            'attendance_clock_out.date_format' => 'Format jam pulang tidak valid.',
        ]);

        $qrisPayload = (string) ($validated['qris_payload'] ?? '');
        if ($qrisPayload !== '') {
            $check = $qrisDynamic->validate($qrisPayload);
            if (! ($check['valid'] ?? false)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'qris_payload' => implode(' ', $check['errors'] ?? ['String QRIS tidak valid.']),
                    ]);
            }
        }

        $requiredEmployeeIds = collect($validated['attendance_required_employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Abaikan pegawai terhubung ke akun root.
        $requiredEmployeeIds = Employee::query()
            ->forAttendance()
            ->whereIn('id', $requiredEmployeeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Simpan juga user_id terkait (untuk kompatibilitas middleware lama)
        $linkedUserIds = Employee::query()
            ->whereIn('id', $requiredEmployeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $payload = [
            'shop_name' => trim($validated['shop_name']),
            'shop_title' => trim((string) ($validated['shop_title'] ?? '')),
            'qris_payload' => $qrisPayload,
            'attendance_enabled' => $request->boolean('attendance_enabled') ? '1' : '0',
            'attendance_clock_in' => $validated['attendance_clock_in'],
            'attendance_clock_out' => $validated['attendance_clock_out'],
            'attendance_early_minutes' => (string) $validated['attendance_early_minutes'],
            'attendance_latitude' => isset($validated['attendance_latitude'])
                ? (string) $validated['attendance_latitude']
                : '',
            'attendance_longitude' => isset($validated['attendance_longitude'])
                ? (string) $validated['attendance_longitude']
                : '',
            'attendance_radius_meters' => (string) $validated['attendance_radius_meters'],
            'attendance_required_employee_ids' => implode(',', $requiredEmployeeIds),
            'attendance_required_user_ids' => implode(',', $linkedUserIds),
            'salary_default_deduction' => (string) Format::parseRupiah($validated['salary_default_deduction'] ?? 0),
            'salary_deduction_late' => (string) Format::parseRupiah($validated['salary_deduction_late'] ?? 0),
            'salary_late_after_minutes' => (string) max(0, (int) ($validated['salary_late_after_minutes'] ?? 0)),
            'salary_deduction_alpha' => (string) Format::parseRupiah($validated['salary_deduction_alpha'] ?? 0),
            'salary_deduction_izin' => (string) Format::parseRupiah($validated['salary_deduction_izin'] ?? 0),
            'salary_deduction_sakit' => (string) Format::parseRupiah($validated['salary_deduction_sakit'] ?? 0),
        ];

        $currentLogo = ShopSettings::get('logo_path');

        if ($request->boolean('remove_logo') && $currentLogo) {
            ShopSettings::deleteLogoFile($currentLogo);
            $payload['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($currentLogo) {
                ShopSettings::deleteLogoFile($currentLogo);
            }
            $payload['logo_path'] = ShopSettings::storeLogo($request->file('logo'));
        }

        $currentQris = ShopSettings::get('qris_path');

        if ($request->boolean('remove_qris') && $currentQris) {
            ShopSettings::deleteQrisFile($currentQris);
            $payload['qris_path'] = null;
        } elseif ($request->hasFile('qris')) {
            if ($currentQris) {
                ShopSettings::deleteQrisFile($currentQris);
            }
            $payload['qris_path'] = ShopSettings::storeQris($request->file('qris'));
        }

        ShopSettings::put($payload);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Pengaturan disimpan. Daftar pegawai wajib absen diperbarui.');
    }

    private function normalizeClockInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{1,2}:\d{2})/', $raw, $matches) !== 1) {
            return $raw;
        }

        [$hour, $minute] = array_map('intval', explode(':', $matches[1]));

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return $raw;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
