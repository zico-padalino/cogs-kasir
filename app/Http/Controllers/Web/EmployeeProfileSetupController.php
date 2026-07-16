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
            'position' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'string'],
            'descriptor' => ['nullable', 'string'],
        ], [
            'phone.required' => 'Nomor telepon wajib diisi.',
            'position.required' => 'Jabatan wajib diisi.',
        ]);

        $employee->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => trim($validated['phone']),
            'position' => trim($validated['position']),
            'department' => filled($validated['department'] ?? null)
                ? trim((string) $validated['department'])
                : $employee->department,
        ]);

        $employee = $employee->fresh();

        if (! $employee->hasFaceEnrollment()) {
            if (! filled($validated['photo'] ?? null) || ! filled($validated['descriptor'] ?? null)) {
                return back()->withInput()->with('error', 'Wajah wajib didaftarkan dari kamera.');
            }

            $descriptor = json_decode((string) $validated['descriptor'], true);
            if (! is_array($descriptor)) {
                return back()->withInput()->with('error', 'Data wajah tidak valid. Coba ambil ulang.');
            }

            try {
                $attendanceService->enrollFace($employee, $validated['photo'], $descriptor);
            } catch (RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            $employee = $employee->fresh();
        } elseif (filled($validated['photo'] ?? null) && filled($validated['descriptor'] ?? null)) {
            $descriptor = json_decode((string) $validated['descriptor'], true);
            if (is_array($descriptor)) {
                try {
                    $attendanceService->enrollFace($employee, $validated['photo'], $descriptor);
                    $employee = $employee->fresh();
                } catch (RuntimeException $e) {
                    return back()->withInput()->with('error', $e->getMessage());
                }
            }
        }

        if (! $employee->isProfileComplete()) {
            $missing = implode(', ', $employee->missingProfileFields());

            return back()->withInput()->with(
                'error',
                'Lengkapi dulu: '.$missing.'.',
            );
        }

        return redirect()
            ->to($user->preferredLoginUrl())
            ->with('success', 'Data karyawan & wajah tersimpan. Silakan lanjut.');
    }
}
