<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Support\KasirPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => array_merge(KasirPin::statusPayload(), [
                'ttl_minutes' => KasirPin::idleMinutes(),
                'shop_name' => config('pos.shop_name'),
            ]),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'data' => array_merge(KasirPin::statusPayload(), [
                'ttl_minutes' => KasirPin::idleMinutes(),
            ]),
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
            'message' => 'Kasir dibuka oleh '.$operator->name.'. Sesi PIN '.KasirPin::idleMinutes().' menit.',
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
