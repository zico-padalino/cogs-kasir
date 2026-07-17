<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\KasirPin;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if ($user->accessibleModules() === []) {
            throw ValidationException::withMessages([
                'email' => 'Akun ini belum memiliki akses modul.',
            ]);
        }

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        KasirPin::lock();

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'shop' => [
                    'name' => config('pos.shop_name'),
                    'title' => config('pos.shop_title'),
                    'logo_url' => ShopSettings::logoUrl(),
                ],
                'pin' => KasirPin::statusPayload(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        KasirPin::lock();

        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'modules' => $user->moduleValues(),
            'must_change_password' => (bool) $user->must_change_password,
            'is_root' => $user->isRoot(),
            'has_kasir' => $user->isKasir(),
            'has_cogs' => $user->isCogs(),
            'has_admin' => $user->isAdmin(),
        ];
    }
}
