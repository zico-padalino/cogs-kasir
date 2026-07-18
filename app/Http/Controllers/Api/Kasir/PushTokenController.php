<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:768'],
            'platform' => ['nullable', 'in:expo'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => DevicePushToken::PLATFORM_EXPO,
                'token_hash' => DevicePushToken::hashToken($validated['token']),
            ],
            [
                'token' => $validated['token'],
                'user_id' => $request->user()?->id,
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Push token tersimpan.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:768'],
        ]);

        DevicePushToken::query()
            ->where('platform', DevicePushToken::PLATFORM_EXPO)
            ->where('token_hash', DevicePushToken::hashToken($validated['token']))
            ->delete();

        return response()->json([
            'message' => 'Push token dihapus.',
        ]);
    }
}
