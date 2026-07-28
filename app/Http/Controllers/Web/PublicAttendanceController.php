<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PublicAttendanceController extends Controller
{
    public function show(Request $request, AttendanceService $attendanceService): View|RedirectResponse
    {
        if (! $attendanceService->isEnabled()) {
            return view('attendance.scan-disabled', [
                'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            ]);
        }

        $user = $request->user();
        $settings = $attendanceService->settings();
        $selectedEmployeeId = $user
            ? $attendanceService->employeeFor($user)?->id
            : null;

        return view('attendance.scan', [
            'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            'settings' => $settings,
            'employees' => $attendanceService->activeEmployeesForScan(),
            'nowLabel' => now()->translatedFormat('l, d M Y'),
            'selectedEmployeeId' => $selectedEmployeeId,
            'continueUrl' => $user?->postAuthUrl(),
        ]);
    }

    public function store(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        if (! $attendanceService->isEnabled()) {
            return back()->with('error', 'Absensi sedang nonaktif.');
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
            'mode' => ['required', 'in:check_in,check_out'],
        ], [
            'employee_id.required' => 'Pilih nama pegawai.',
            'latitude.required' => 'Lokasi GPS wajib diaktifkan.',
            'photo.required' => 'Selfie wajib diambil.',
            'mode.required' => 'Mode absensi tidak valid.',
        ]);

        $employee = Employee::query()->forAttendance()->find($validated['employee_id']);

        if (! $employee) {
            return back()->withInput()->with('error', 'Pegawai tidak ditemukan atau tidak boleh absen.');
        }

        $expected = $attendanceService->actionForEmployee($employee);
        if ($expected !== $validated['mode']) {
            return back()->withInput()->with('error', match ($expected) {
                'check_in' => 'Saat ini yang tersedia: Absen Masuk.',
                'check_out' => 'Saat ini yang tersedia: Absen Pulang.',
                'done' => 'Anda sudah absen masuk & pulang hari ini.',
                default => 'Di luar jam absensi untuk pegawai ini.',
            });
        }

        try {
            if ($validated['mode'] === 'check_out') {
                $attendanceService->checkOut(
                    $employee,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['photo'],
                );
                $message = 'Absen pulang berhasil — '.$employee->name;
            } else {
                $attendanceService->checkIn(
                    $employee,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['photo'],
                );
                $message = 'Absen masuk berhasil — '.$employee->name;
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $user = $request->user();
        if ($user && $attendanceService->requiredAction($user) === null) {
            // Absen akun login selesai → kembali ke modul yang tadi dibuka (kasir/hub/dll).
            return redirect()
                ->intended($user->postAuthUrl())
                ->with('success', $message);
        }

        return redirect()
            ->route('attendance.scan')
            ->with('success', $message);
    }
}
