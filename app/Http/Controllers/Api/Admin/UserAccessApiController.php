<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAccessApiController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()->orderBy('name')->get();

        return response()->json([
            'message' => 'Daftar akun berhasil dimuat.',
            'data' => [
                'users' => $users,
                'all_modules' => array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'Detail akun berhasil dimuat.',
            'data' => $user,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $defaultPassword = (string) config('pos.default_user_password', 'password');

        $modules = $validated['is_root']
            ? array_map(fn (UserRole $role) => $role->value, UserRole::cases())
            : $validated['modules'];

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $defaultPassword,
            'must_change_password' => true,
            'is_root' => $validated['is_root'],
            'role' => UserRole::from($modules[0]),
            'modules' => $modules,
        ]);

        return response()->json([
            'message' => 'Akun '.$user->name.' berhasil dibuat. Password sementara: '.$defaultPassword.'. Saat login pertama, user wajib mengganti password.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->isRoot() && ! $request->user()->isRoot()) {
            return response()->json([
                'message' => 'Hanya akun root yang dapat mengubah akun root.',
            ], 403);
        }

        $validated = $this->validated($request, $user);

        $modules = $validated['is_root']
            ? array_map(fn (UserRole $role) => $role->value, UserRole::cases())
            : $validated['modules'];

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_root = $validated['is_root'];
        $user->syncModules($modules);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->must_change_password = true;
        }

        $user->save();

        return response()->json([
            'message' => 'Akses akun berhasil diperbarui.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Tidak bisa menghapus akun yang sedang dipakai.',
            ], 422);
        }

        if ($user->isRoot() && ! $request->user()->isRoot()) {
            return response()->json([
                'message' => 'Hanya akun root yang dapat menghapus akun root.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Akun dihapus.',
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->isRoot() && ! $request->user()->isRoot()) {
            return response()->json([
                'message' => 'Hanya akun root yang dapat mereset password akun root.',
            ], 403);
        }

        $defaultPassword = (string) config('pos.default_user_password', 'password');

        $user->password = $defaultPassword;
        $user->must_change_password = true;
        $user->save();

        return response()->json([
            'message' => 'Password '.$user->name.' direset. Password sementara: '.$defaultPassword.'. Saat login berikutnya, user wajib mengganti password.',
            'data' => [
                'user' => $user->fresh(),
                'temporary_password' => $defaultPassword,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $actorIsRoot = $request->user()->isRoot();
        $willBeRoot = $actorIsRoot
            ? $request->boolean('is_root')
            : ($user?->isRoot() ?? false);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_root' => [$actorIsRoot ? 'sometimes' : 'prohibited', 'boolean'],
            'modules' => [Rule::requiredIf(! $willBeRoot), 'array'],
            'modules.*' => [Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        $validated['is_root'] = $willBeRoot;
        $validated['modules'] = array_values(array_unique($validated['modules'] ?? []));

        return $validated;
    }
}
