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
    /**
     * Halaman absensi QR publik — tanpa login.
     * Pegawai cukup scan QR → pilih nama → selfie + GPS.
     */
    public function show(AttendanceService $attendanceService): View
    {
        if (! $attendanceService->isEnabled()) {
            return view('attendance.scan-disabled', [
                'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            ]);
        }

        $settings = $attendanceService->settings();

        return view('attendance.scan', [
            'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            'settings' => $settings,
            'employees' => $attendanceService->activeEmployeesForScan(),
            'nowLabel' => now()->translatedFormat('l, d M Y'),
            'selectedEmployeeId' => null,
            'continueUrl' => null,
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
        $available = $attendanceService->availableActionsForEmployee($employee);
        if (! in_array($validated['mode'], $available, true)) {
            return back()->withInput()->with('error', match (true) {
                $validated['mode'] === 'check_in' && in_array('check_out', $available, true)
                    => 'Saat ini Anda perlu Absen Pulang dulu, atau pilih Absen Masuk jika ingin lanjut (ketidakhadiran pulang akan tercatat).',
                in_array('check_in', $available, true) => 'Saat ini yang tersedia: Absen Masuk.',
                in_array('check_out', $available, true) => 'Saat ini yang tersedia: Absen Pulang.',
                $expected === 'done' => 'Anda sudah absen masuk & pulang hari ini.',
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
                $hadMissed = $attendanceService->missedCheckoutAttendance($employee) !== null;
                $attendanceService->checkIn(
                    $employee,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['photo'],
                );
                $message = 'Absen masuk berhasil — '.$employee->name;
                if ($hadMissed) {
                    $message .= '. Catatan: ketidakhadiran absen pulang shift sebelumnya telah tercatat.';
                }
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('attendance.scan')
            ->with('success', $message);
    }
}
