<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password', [
            'layout' => $this->layoutName(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password baru harus berbeda dari password saat ini.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route('password.edit')
            ->with('success', 'Password berhasil diubah.');
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
