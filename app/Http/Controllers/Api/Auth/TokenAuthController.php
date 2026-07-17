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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class TokenAuthController extends Controller
{
    public function shop(): JsonResponse
    {
        return response()->json([
            'data' => [
                'name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                'title' => ShopSettings::get('shop_title', config('pos.shop_title')) ?: 'Masuk untuk mengelola toko Anda',
                'logo_url' => ShopSettings::logoUrl(),
                'initial' => ShopSettings::initial(),
            ],
        ]);
    }

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

        if (! Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'message' => 'Tabel personal_access_tokens belum ada. Jalankan migrasi di server (cPanel → Terminal / php artisan migrate).',
                'code' => 'TOKEN_TABLE_MISSING',
            ], 500);
        }

        try {
            $user->tokens()->where('name', 'mobile')->delete();
            $token = $user->createToken('mobile')->plainTextToken;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal membuat token login: '.$e->getMessage(),
                'code' => 'TOKEN_CREATE_FAILED',
            ], 500);
        }

        try {
            KasirPin::lock();
        } catch (Throwable) {
            // abaikan — login tetap sukses
        }

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
        $attendance = app(\App\Services\AttendanceService::class);

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'shop' => [
                    'name' => ShopSettings::get('shop_name', config('pos.shop_name')),
                    'title' => ShopSettings::get('shop_title', config('pos.shop_title')),
                    'logo_url' => ShopSettings::logoUrl(),
                    'initial' => ShopSettings::initial(),
                ],
                'pin' => KasirPin::statusPayload(),
                'attendance' => [
                    'enabled' => $attendance->isEnabled(),
                    'must_attend' => $attendance->mustAttend($user),
                    'profile_required' => $attendance->needsProfileSetup($user),
                    'required_action' => $attendance->requiredAction($user),
                ],
                'modules' => collect($user->accessibleModules())->map(fn ($role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])->values(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            KasirPin::lock();
        } catch (Throwable) {
            // ignore
        }

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
