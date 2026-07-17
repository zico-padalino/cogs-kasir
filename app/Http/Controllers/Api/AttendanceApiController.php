<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceApiController extends Controller
{
    /** Status absensi untuk user yang sedang login (mobile gate). */
    public function status(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $profileRequired = $attendanceService->needsProfileSetup($user);
        $action = $attendanceService->requiredAction($user);

        return response()->json([
            'data' => [
                'enabled' => $attendanceService->isEnabled(),
                'must_attend' => $attendanceService->mustAttend($user),
                'profile_required' => $profileRequired,
                'required_action' => $action,
                'can_access_app' => ! $profileRequired && $action === null,
                'settings' => $attendanceService->settings(),
            ],
        ]);
    }

    /** Data halaman scan publik (daftar pegawai + setting GPS). */
    public function scanShow(AttendanceService $attendanceService): JsonResponse
    {
        if (! $attendanceService->isEnabled()) {
            return response()->json([
                'message' => 'Absensi sedang nonaktif.',
                'data' => [
                    'enabled' => false,
                    'shop_name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                ],
            ]);
        }

        $employees = $attendanceService->activeEmployeesForScan()->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'action' => $attendanceService->actionForEmployee($e),
        ])->values();

        return response()->json([
            'data' => [
                'enabled' => true,
                'shop_name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                'settings' => $attendanceService->settings(),
                'employees' => $employees,
                'now_label' => now()->translatedFormat('l, d M Y'),
            ],
        ]);
    }

    public function scanStore(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        if (! $attendanceService->isEnabled()) {
            return response()->json(['message' => 'Absensi sedang nonaktif.'], 422);
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

        $employee = Employee::query()->findOrFail($validated['employee_id']);

        if ($employee->status !== EmployeeStatus::Active) {
            return response()->json(['message' => 'Pegawai tidak aktif.'], 422);
        }

        $expected = $attendanceService->actionForEmployee($employee);
        if ($expected !== $validated['mode']) {
            return response()->json([
                'message' => match ($expected) {
                    'check_in' => 'Saat ini yang tersedia: Absen Masuk.',
                    'check_out' => 'Saat ini yang tersedia: Absen Pulang.',
                    'done' => 'Anda sudah absen masuk & pulang hari ini.',
                    default => 'Di luar jam absensi untuk pegawai ini.',
                },
            ], 422);
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'employee_id' => $employee->id,
                'mode' => $validated['mode'],
            ],
        ]);
    }
}
