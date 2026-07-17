<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleHubController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $modules = collect($user->accessibleModules())->map(fn (UserRole $role) => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'home' => match ($role) {
                UserRole::Kasir => '/kasir',
                UserRole::Cogs => '/cogs',
                UserRole::Admin => '/admin',
            },
        ])->values();

        return response()->json([
            'data' => [
                'modules' => $modules,
                'default' => $user->defaultModule()->value,
                'preferred' => $user->preferredLoginModule()->value,
            ],
        ]);
    }

    public function switch(Request $request, string $module): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = UserRole::tryFrom($module);

        if (! $role || ! $user->hasModule($role)) {
            return response()->json([
                'message' => 'Modul tidak tersedia untuk akun ini.',
                'code' => 'FORBIDDEN_MODULE',
            ], 403);
        }

        return response()->json([
            'message' => 'Modul dipilih.',
            'data' => [
                'module' => $role->value,
                'home' => match ($role) {
                    UserRole::Kasir => '/kasir',
                    UserRole::Cogs => '/cogs',
                    UserRole::Admin => '/admin',
                },
            ],
        ]);
    }
}
