<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\CogsNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** @return list<UserRole> */
    private function loginModules(): array
    {
        return [UserRole::Cogs, UserRole::Kasir];
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user) {
            return redirect()->to($this->homeFor($user));
        }

        return view('auth.login', [
            'modules' => $this->loginModules(),
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

        if ($user->hasModule(UserRole::Admin)) {
            $request->session()->regenerate();
            $request->session()->put('auth_module', UserRole::Admin->value);

            return redirect()->intended(route('admin.dashboard'));
        }

        if (! $user->hasModule($module)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses modul '.$module->label().'.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_module', $module->value);

        if ($module === UserRole::Cogs) {
            return redirect()->to(CogsNavigation::preferredUrl());
        }

        return redirect()->intended(route($module->homeRoute()));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function homeFor(User $user): string
    {
        if ($user->hasModule(UserRole::Admin)) {
            return route('admin.dashboard');
        }

        if (count($user->accessibleModules()) > 1) {
            return route('hub');
        }

        if ($user->hasModule(UserRole::Cogs)) {
            return CogsNavigation::preferredUrl();
        }

        return route($user->defaultModule()->homeRoute());
    }
}
