<?php

namespace App\Http\Controllers\Api;

use App\Enums\OverheadAllocationBase;
use App\Http\Controllers\Controller;
use App\Models\OverheadRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OverheadRateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OverheadRate::latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'allocation_base' => ['required', Rule::enum(OverheadAllocationBase::class)],
            'rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rate = OverheadRate::create($validated);

        return response()->json([
            'message' => 'Overhead rate berhasil dibuat.',
            'data' => $rate,
        ], 201);
    }

    public function update(Request $request, OverheadRate $overheadRate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'allocation_base' => ['sometimes', Rule::enum(OverheadAllocationBase::class)],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $overheadRate->update($validated);

        return response()->json([
            'message' => 'Overhead rate berhasil diperbarui.',
            'data' => $overheadRate->fresh(),
        ]);
    }

    public function destroy(OverheadRate $overheadRate): JsonResponse
    {
        $overheadRate->delete();

        return response()->json([
            'message' => 'Overhead rate berhasil dihapus.',
        ]);
    }
}
