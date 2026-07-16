<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = ShopSettings::all();
        $requiredIds = array_values(array_filter(array_map(
            'intval',
            preg_split('/[\s,]+/', (string) ($settings['attendance_required_user_ids'] ?? '')) ?: [],
        )));

        return view('admin.settings.edit', [
            'settings' => $settings,
            'logoUrl' => ShopSettings::logoUrl(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'requiredUserIds' => $requiredIds,
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        $request->merge([
            'attendance_latitude' => $request->filled('attendance_latitude')
                ? $request->input('attendance_latitude')
                : null,
            'attendance_longitude' => $request->filled('attendance_longitude')
                ? $request->input('attendance_longitude')
                : null,
        ]);

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:80'],
            'shop_title' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'attendance_enabled' => ['sometimes', 'boolean'],
            'attendance_clock_in' => ['required', 'date_format:H:i'],
            'attendance_clock_out' => ['required', 'date_format:H:i'],
            'attendance_early_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_radius_meters' => ['required', 'numeric', 'min:10', 'max:5000'],
            'attendance_required_user_ids' => ['nullable', 'array'],
            'attendance_required_user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'shop_name.required' => 'Nama toko wajib diisi.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'attendance_clock_in.date_format' => 'Format jam masuk tidak valid.',
            'attendance_clock_out.date_format' => 'Format jam pulang tidak valid.',
        ]);

        $requiredIds = collect($validated['attendance_required_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $payload = [
            'shop_name' => trim($validated['shop_name']),
            'shop_title' => trim((string) ($validated['shop_title'] ?? '')),
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
            'attendance_required_user_ids' => implode(',', $requiredIds),
        ];

        $currentLogo = ShopSettings::get('logo_path');

        if ($request->boolean('remove_logo') && $currentLogo) {
            Storage::disk('public')->delete($currentLogo);
            $payload['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($currentLogo) {
                Storage::disk('public')->delete($currentLogo);
            }
            $payload['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        ShopSettings::put($payload);

        // Akun yang dicentang wajib absen → otomatis masuk Data Karyawan
        $attendanceService->syncRequiredEmployees($requiredIds);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Pengaturan disimpan. Data karyawan untuk akun wajib absen sudah disiapkan.');
    }
}
