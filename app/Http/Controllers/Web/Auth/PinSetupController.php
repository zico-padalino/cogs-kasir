<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Support\KasirPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinSetupController extends Controller
{
    public function edit(AttendanceService $attendanceService): View
    {
        $user = auth()->user();
        $employee = $attendanceService->ensureEmployeeForUser($user);

        return view('auth.pin-setup', [
            'layout' => $this->layoutName(),
            'hasPin' => KasirPin::hasPin($employee),
            'canUseKasir' => $user->hasModule(UserRole::Kasir),
        ]);
    }

    public function update(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasModule(UserRole::Kasir)) {
            return redirect()
                ->route('password.edit')
                ->with('error', 'PIN kasir hanya untuk akun yang punya akses modul Kasir.');
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

        if (! KasirPin::isUnlocked() && $user->hasModule(UserRole::Kasir)) {
            return redirect()
                ->route('kasir.pin.unlock')
                ->with('success', 'PIN berhasil disimpan. Masukkan PIN untuk membuka kasir.');
        }

        return redirect()
            ->route('pin.edit')
            ->with('success', 'PIN kasir berhasil disimpan.');
    }

    private function layoutName(): string
    {
        $module = session('auth_module');

        if (! is_string($module) || UserRole::tryFrom($module) === null) {
            $module = auth()->user()?->defaultModule()->value;
        }

        return match ($module) {
            UserRole::Admin->value => 'layouts.admin',
            UserRole::Kasir->value => 'layouts.kasir',
            default => 'layouts.app',
        };
    }
}
