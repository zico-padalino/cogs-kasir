<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Http\Controllers\Controller;
use App\Services\ResetDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetDataApiController extends Controller
{
    public function show(ResetDataService $resetService): JsonResponse
    {
        return response()->json([
            'message' => 'Informasi reset data.',
            'data' => [
                'counts' => $resetService->counts(),
            ],
        ]);
    }

    public function reset(Request $request, ResetDataService $resetService): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESET'],
        ]);

        $resetService->resetAll();

        return response()->json([
            'message' => 'Semua data berhasil dihapus. Database sekarang kosong.',
            'data' => [
                'counts' => $resetService->counts(),
            ],
        ]);
    }
}
