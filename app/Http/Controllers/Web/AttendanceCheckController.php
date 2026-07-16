<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AttendanceCheckController extends Controller
{
    public function checkIn(Request $request, AttendanceService $attendanceService): View|RedirectResponse
    {
        return $this->show($request, $attendanceService, 'check_in');
    }

    public function checkOut(Request $request, AttendanceService $attendanceService): View|RedirectResponse
    {
        return $this->show($request, $attendanceService, 'check_out');
    }

    public function storeCheckIn(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        return $this->store($request, $attendanceService, 'check_in');
    }

    public function storeCheckOut(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        return $this->store($request, $attendanceService, 'check_out');
    }

    private function show(Request $request, AttendanceService $attendanceService, string $mode): View|RedirectResponse
    {
        $user = $request->user();
        $required = $attendanceService->requiredAction($user);

        if ($required === null) {
            return redirect()->to($user->preferredLoginUrl());
        }

        if ($required !== $mode) {
            return redirect()->route($required === 'check_out' ? 'attendance.check-out' : 'attendance.check-in');
        }

        $employee = $attendanceService->employeeFor($user);
        $settings = $attendanceService->settings();

        return view('attendance.check', [
            'mode' => $mode,
            'employee' => $employee,
            'settings' => $settings,
            'hasFace' => $employee?->hasFaceEnrollment() ?? false,
            'user' => $user,
        ]);
    }

    private function store(Request $request, AttendanceService $attendanceService, string $mode): RedirectResponse
    {
        $user = $request->user();
        $employee = $attendanceService->employeeFor($user);

        if (! $employee) {
            return redirect()->to($user->preferredLoginUrl())->with('error', 'Akun belum terhubung ke data karyawan.');
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
            'descriptor' => ['required', 'string'],
        ], [
            'latitude.required' => 'Lokasi GPS wajib diaktifkan.',
            'photo.required' => 'Foto wajah wajib diambil.',
            'descriptor.required' => 'Wajah belum terdeteksi. Pastikan kamera menghadap wajah.',
        ]);

        $descriptor = json_decode($validated['descriptor'], true);
        if (! is_array($descriptor)) {
            return back()->with('error', 'Data wajah tidak valid. Coba lagi.');
        }

        try {
            if ($mode === 'check_out') {
                $attendanceService->checkOut(
                    $employee,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['photo'],
                    $descriptor,
                );
                $message = 'Absen pulang berhasil. Terima kasih.';
            } else {
                $attendanceService->checkIn(
                    $employee,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $validated['photo'],
                    $descriptor,
                );
                $message = 'Absen masuk berhasil. Selamat bekerja.';
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to($user->preferredLoginUrl())->with('success', $message);
    }
}
