<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\KasirPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinSetupController extends Controller
{
    public function show(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $attendanceService->ensureEmployeeForUser($user);

        return response()->json([
            'data' => [
                'has_pin' => KasirPin::hasPin($employee),
                'can_use_kasir' => $user->hasModule(UserRole::Kasir),
            ],
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasModule(UserRole::Kasir)) {
            return response()->json([
                'message' => 'PIN kasir hanya untuk akun yang punya akses modul Kasir.',
                'code' => 'FORBIDDEN_MODULE',
            ], 403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'pin' => ['required', 'digits_between:4,6'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'current_password.required' => 'Password akun wajib diisi untuk mengamankan PIN.',
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits_between' => 'PIN harus 4–6 digit angka.',
            'pin_confirmation.same' => 'Konfirmasi PIN tidak cocok.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password akun tidak sesuai.',
            ]);
        }

        $employee = $attendanceService->ensureEmployeeForUser($user);

        $existing = KasirPin::findByPin($validated['pin']);
        if ($existing && $existing->id !== $employee->id) {
            throw ValidationException::withMessages([
                'pin' => 'PIN ini sudah dipakai karyawan lain. Pilih PIN berbeda.',
            ]);
        }

        KasirPin::setPin($employee, $validated['pin']);

        return response()->json([
            'message' => 'PIN kasir berhasil disimpan.',
            'data' => [
                'has_pin' => true,
            ],
        ]);
    }
}
