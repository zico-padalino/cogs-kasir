<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\CogsNavigation;
use Illuminate\Http\Request;

class ModuleHubController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $modules = $user->accessibleModules();

        if (count($modules) <= 1) {
            $module = $user->defaultModule();

            if ($module === UserRole::Cogs) {
                return redirect()->to(CogsNavigation::preferredUrl());
            }

            return redirect()->route($module->homeRoute());
        }

        return view('auth.hub', [
            'modules' => $modules,
            'user' => $user,
        ]);
    }

    public function switch(Request $request, string $module)
    {
        $role = UserRole::tryFrom($module);
        $user = $request->user();

        if (! $role || ! $user->hasModule($role)) {
            return redirect()->route('hub')->with('error', 'Modul tidak tersedia untuk akun ini.');
        }

        $request->session()->put('auth_module', $role->value);

        if ($role === UserRole::Cogs) {
            return redirect()->to(CogsNavigation::preferredUrl());
        }

        return redirect()->route($role->homeRoute());
    }
}
