<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Support\KasirPin;
use App\Support\SessionPressure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    public function show(): JsonResponse
    {
        $payload = array_merge(KasirPin::statusPayload(), [
            'ttl_minutes' => KasirPin::idleMinutes(),
            'shop_name' => config('pos.shop_name'),
        ]);
        SessionPressure::releaseEarly();

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function status(): JsonResponse
    {
        $payload = array_merge(KasirPin::statusPayload(), [
            'ttl_minutes' => KasirPin::idleMinutes(),
        ]);
        SessionPressure::releaseEarly();

        return response()->json([
            'data' => $payload,
        ]);
    }

    /** Perpanjang sesi PIN karena ada aktivitas di app (idle timer). */
    public function touch(): JsonResponse
    {
        SessionPressure::releaseEarly();

        $userId = auth()->id() ?: 0;
        $throttleKey = 'kasir_pin_touch_throttle:'.$userId;

        try {
            $store = \Illuminate\Support\Facades\Cache::store('file');
            if ($store->has($throttleKey)) {
                return response()->json([
                    'data' => array_merge(KasirPin::statusPayload(), [
                        'ttl_minutes' => KasirPin::idleMinutes(),
                        'touch_throttled' => true,
                    ]),
                ])->header('Cache-Control', 'private, max-age=10');
            }
            $store->put($throttleKey, 1, 90);
        } catch (\Throwable) {
            // lanjut touch normal
        }

        KasirPin::touch();
        $payload = array_merge(KasirPin::statusPayload(), [
            'ttl_minutes' => KasirPin::idleMinutes(),
        ]);

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits_between' => 'PIN harus 4–6 digit.',
        ]);

        $operator = KasirPin::findByPin($validated['pin']);

        if (! $operator) {
            throw ValidationException::withMessages([
                'pin' => 'PIN tidak dikenali. Coba lagi.',
            ]);
        }

        KasirPin::unlock($operator);

        return response()->json([
            'message' => 'Kasir dibuka oleh '.$operator->name.'.',
            'data' => array_merge(KasirPin::statusPayload(), [
                'ttl_minutes' => KasirPin::idleMinutes(),
                'operator' => [
                    'id' => $operator->id,
                    'name' => $operator->name,
                ],
            ]),
        ]);
    }

    public function lock(): JsonResponse
    {
        $name = KasirPin::operatorName();
        KasirPin::lock();

        return response()->json([
            'message' => 'Sesi '.$name.' dikunci. Masukkan PIN untuk membuka lagi.',
            'data' => KasirPin::statusPayload(),
        ]);
    }
}
