<?php

namespace App\Http\Controllers\Web;

use App\Enums\OverheadAllocationBase;
use App\Http\Controllers\Controller;
use App\Models\OverheadRate;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OverheadRateController extends Controller
{
    public function index()
    {
        $rates = OverheadRate::latest()->get();

        $allocationBases = [
            OverheadAllocationBase::DirectMaterial,
            OverheadAllocationBase::DirectLabor,
            OverheadAllocationBase::LaborHours,
        ];

        return view('overhead-rates.index', [
            'rates' => $rates,
            'allocationBases' => $allocationBases,
            'format' => Format::class,
        ]);
    }

    public function create()
    {
        return redirect()->route('overhead-rates.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'allocation_base' => ['required', Rule::enum(OverheadAllocationBase::class)],
            'rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        OverheadRate::create($validated);

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya berhasil ditambahkan.');
    }

    public function edit(OverheadRate $overheadRate)
    {
        return view('overhead-rates.edit', [
            'rate' => $overheadRate,
            'allocationBases' => [
                OverheadAllocationBase::DirectMaterial,
                OverheadAllocationBase::DirectLabor,
                OverheadAllocationBase::LaborHours,
            ],
        ]);
    }

    public function update(Request $request, OverheadRate $overheadRate)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'allocation_base' => ['required', Rule::enum(OverheadAllocationBase::class)],
            'rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $overheadRate->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya berhasil diperbarui.');
    }

    public function destroy(OverheadRate $overheadRate)
    {
        $overheadRate->delete();

        return redirect()->route('overhead-rates.index')->with('success', 'Biaya dihapus.');
    }
}
