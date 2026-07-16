<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EmployeeProfileSetupController extends Controller
{
    public function edit(Request $request, AttendanceService $attendanceService): View|RedirectResponse
    {
        $user = $request->user();

        if (! $attendanceService->mustAttend($user)) {
            return redirect()->to($user->preferredLoginUrl());
        }

        $employee = $attendanceService->ensureEmployeeForUser($user);

        if ($employee->isProfileComplete()) {
            return redirect()->to($user->preferredLoginUrl());
        }

        return view('attendance.profile-setup', [
            'user' => $user,
            'employee' => $employee,
            'missing' => $employee->missingProfileFields(),
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        $user = $request->user();

        if (! $attendanceService->mustAttend($user)) {
            return redirect()->to($user->preferredLoginUrl());
        }

        $employee = $attendanceService->ensureEmployeeForUser($user);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'photo' => ['nullable', 'string'],
            'descriptor' => ['nullable', 'string'],
        ], [
            'phone.required' => 'Nomor telepon wajib diisi.',
        ]);

        $employee->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => trim($validated['phone']),
        ]);

        $employee = $employee->fresh();

        if (! $employee->hasFaceEnrollment()) {
            if (! filled($validated['photo'] ?? null) || ! filled($validated['descriptor'] ?? null)) {
                return back()->withInput()->with('error', 'Ikuti instruksi wajah sampai selesai, lalu simpan.');
            }

            $descriptor = json_decode((string) $validated['descriptor'], true);
            if (! is_array($descriptor)) {
                return back()->withInput()->with('error', 'Data wajah tidak valid. Coba ulangi panduan wajah.');
            }

            try {
                $attendanceService->enrollFace($employee, $validated['photo'], $descriptor);
            } catch (RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            $employee = $employee->fresh();
        }

        if (! $employee->isProfileComplete()) {
            $missing = implode(', ', $employee->missingProfileFields());

            return back()->withInput()->with('error', 'Lengkapi dulu: '.$missing.'.');
        }

        return redirect()
            ->to($user->preferredLoginUrl())
            ->with('success', 'Profil & wajah tersimpan. Silakan lanjut.');
    }
}
