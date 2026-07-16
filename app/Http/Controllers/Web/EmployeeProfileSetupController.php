<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        ], [
            'phone.required' => 'Nomor telepon wajib diisi.',
        ]);

        $employee->update([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => trim($validated['phone']),
        ]);

        return redirect()
            ->to($user->preferredLoginUrl())
            ->with('success', 'Profil tersimpan. Silakan lanjut.');
    }
}
