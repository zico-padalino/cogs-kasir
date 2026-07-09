<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ModuleHubController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $modules = $user->accessibleModules();

        if (count($modules) <= 1) {
            return redirect()->route($user->defaultModule()->homeRoute());
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

        return redirect()->route($role->homeRoute());
    }
}
