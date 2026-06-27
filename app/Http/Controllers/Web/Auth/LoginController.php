<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user()) {
            return redirect()->route($request->user()->role->homeRoute());
        }

        return view('auth.login', [
            'modules' => UserRole::cases(),
        ]);
    }

    public function store(LoginRequest $request)
    {
        $module = UserRole::from($request->validated('module'));
        $remember = $request->boolean('remember');

        if (! Auth::attempt($request->only('email', 'password'), $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $user = $request->user();

        if ($user->role !== $module) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses modul '.$module->label().'.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_module', $module->value);

        return redirect()->intended(route($module->homeRoute()));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
