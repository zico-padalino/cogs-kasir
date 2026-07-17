<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->bearerToken($request);

        if (! $plain) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'message' => 'Tabel personal_access_tokens belum ada. Jalankan migrasi di server.',
                'code' => 'TOKEN_TABLE_MISSING',
            ], 500);
        }

        [$id, $token] = array_pad(explode('|', $plain, 2), 2, null);

        if (! $id || ! $token) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        /** @var PersonalAccessToken|null $accessToken */
        $accessToken = PersonalAccessToken::query()->find($id);

        if (! $accessToken || ! hash_equals((string) $accessToken->token, hash('sha256', $token))) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return response()->json([
                'message' => 'Token kedaluwarsa.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        }

        $user = $accessToken->tokenable;

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $user->withAccessToken($accessToken);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
