<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\KasirPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        if ($user) {
            return redirect()->to($user->homeUrl());
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $remember = $request->boolean('remember');

        if (! Auth::attempt($request->only('email', 'password'), $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        if ($user->accessibleModules() === []) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini belum memiliki akses modul.',
            ]);
        }

        $module = $user->hasModule(UserRole::Admin)
            ? UserRole::Admin
            : $user->defaultModule();

        $request->session()->regenerate();
        $request->session()->put('auth_module', $module->value);
        KasirPin::lock();

        if ($user->must_change_password) {
            return redirect()
                ->route('password.edit')
                ->with('error', 'Akun baru wajib mengganti password sementara sebelum lanjut.');
        }

        return redirect()->intended($user->homeUrl());
    }

    public function destroy(Request $request)
    {
        KasirPin::lock();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
