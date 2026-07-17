<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileSetupController extends Controller
{
    public function show(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $attendanceService->mustAttend($user)) {
            return response()->json([
                'data' => [
                    'required' => false,
                    'complete' => true,
                ],
            ]);
        }

        $employee = $attendanceService->ensureEmployeeForUser($user);

        return response()->json([
            'data' => [
                'required' => true,
                'complete' => $employee->isProfileComplete(),
                'missing' => $employee->missingProfileFields(),
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                ],
            ],
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $attendanceService->mustAttend($user)) {
            return response()->json([
                'message' => 'Profil absensi tidak wajib untuk akun ini.',
                'data' => ['required' => false],
            ]);
        }

        $employee = $attendanceService->ensureEmployeeForUser($user);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ], [
            'phone.required' => 'Nomor telepon wajib diisi.',
        ]);

        $employee->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => trim($validated['phone']),
        ]);

        return response()->json([
            'message' => 'Profil tersimpan.',
            'data' => [
                'required' => true,
                'complete' => $employee->fresh()->isProfileComplete(),
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'phone' => $employee->phone,
                ],
            ],
        ]);
    }
}
