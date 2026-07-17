<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Enums\OverheadAllocationBase;
use App\Http\Controllers\Controller;
use App\Models\OverheadRate;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OverheadApiController extends Controller
{
    public function index(): JsonResponse
    {
        $rates = OverheadRate::latest()->get();

        return response()->json([
            'message' => 'Daftar biaya overhead berhasil dimuat.',
            'data' => $rates,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $rate = OverheadRate::create($this->validatedOverheadPayload($request));

        return response()->json([
            'message' => 'Biaya berhasil ditambahkan.',
            'data' => $rate,
        ], 201);
    }

    public function update(Request $request, OverheadRate $overheadRate): JsonResponse
    {
        $payload = $this->validatedOverheadPayload($request, $overheadRate);

        $overheadRate->update([
            ...$payload,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Biaya berhasil diperbarui.',
            'data' => $overheadRate->fresh(),
        ]);
    }

    public function destroy(OverheadRate $overheadRate): JsonResponse
    {
        $overheadRate->delete();

        return response()->json([
            'message' => 'Biaya dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOverheadPayload(Request $request, ?OverheadRate $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cost_mode' => ['required', Rule::in(['percent', 'hourly'])],
            'percent_value' => ['required_if:cost_mode,percent', 'nullable', 'numeric', 'min:0', 'max:100'],
            'hourly_rate' => ['required_if:cost_mode,hourly', 'nullable'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validated['cost_mode'] === 'percent') {
            $percent = (float) ($validated['percent_value'] ?? 0);

            if ($percent <= 0) {
                throw ValidationException::withMessages([
                    'percent_value' => 'Isi persentase lebih dari 0, misalnya 10 untuk 10%.',
                ]);
            }

            $allocationBase = $existing?->allocation_base === OverheadAllocationBase::DirectLabor
                ? OverheadAllocationBase::DirectLabor
                : OverheadAllocationBase::DirectMaterial;

            return [
                'name' => $validated['name'],
                'allocation_base' => $allocationBase,
                'rate' => round($percent / 100, 6),
                'description' => $validated['description'] ?? null,
            ];
        }

        $hourly = Format::parseRupiah((string) ($validated['hourly_rate'] ?? '0'));

        if ($hourly <= 0) {
            throw ValidationException::withMessages([
                'hourly_rate' => 'Isi upah per jam, misalnya 25.000.',
            ]);
        }

        return [
            'name' => $validated['name'],
            'allocation_base' => OverheadAllocationBase::LaborHours,
            'rate' => $hourly,
            'description' => $validated['description'] ?? null,
        ];
    }
}
