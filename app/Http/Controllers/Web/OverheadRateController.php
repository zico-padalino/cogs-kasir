<?php

namespace App\Http\Controllers\Web;

use App\Enums\OverheadAllocationBase;
use App\Http\Controllers\Controller;
use App\Models\OverheadRate;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OverheadRateController extends Controller
{
    public function index()
    {
        $rates = OverheadRate::latest()->get();

        return view('overhead-rates.index', [
            'rates' => $rates,
            'format' => Format::class,
        ]);
    }

    public function create()
    {
        return redirect()->route('overhead-rates.index');
    }

    public function store(Request $request)
    {
        OverheadRate::create($this->validatedOverheadPayload($request));

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya berhasil ditambahkan.');
    }

    public function edit(OverheadRate $overheadRate)
    {
        $base = $overheadRate->allocation_base;
        $costMode = $base->isRatioBased() ? 'percent' : 'hourly';

        return view('overhead-rates.edit', [
            'rate' => $overheadRate,
            'costMode' => $costMode,
            'percentValue' => $base->isRatioBased() ? round((float) $overheadRate->rate * 100, 2) : null,
            'hourlyRate' => ! $base->isRatioBased() ? (float) $overheadRate->rate : null,
            'format' => Format::class,
        ]);
    }

    public function update(Request $request, OverheadRate $overheadRate)
    {
        $payload = $this->validatedOverheadPayload($request, $overheadRate);

        $overheadRate->update([
            ...$payload,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya berhasil diperbarui.');
    }

    public function destroy(OverheadRate $overheadRate)
    {
        $overheadRate->delete();

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya dihapus.');
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
