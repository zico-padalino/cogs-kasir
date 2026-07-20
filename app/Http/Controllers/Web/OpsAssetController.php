<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OpsAsset;
use App\Models\OpsAssetLog;
use App\Services\OpsAssetService;
use App\Support\Format;
use Illuminate\Http\Request;

class OpsAssetController extends Controller
{
    public function index()
    {
        $assets = OpsAsset::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $logs = OpsAssetLog::query()
            ->with(['asset', 'user'])
            ->latest()
            ->limit(100)
            ->get();

        return view('ops-assets.index', [
            'assets' => $assets,
            'logs' => $logs,
            'format' => Format::class,
        ]);
    }

    public function store(Request $request, OpsAssetService $opsService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $opsService->create(
            name: $validated['name'],
            unit: $validated['unit'] ?? 'pcs',
            initialQty: (float) ($validated['quantity'] ?? 0),
            note: $validated['note'] ?? null,
        );

        return back()->with('success', 'Item inventaris operasional ditambahkan.');
    }

    public function receive(Request $request, OpsAsset $opsAsset, OpsAssetService $opsService)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $opsService->receive($opsAsset, (float) $validated['quantity'], $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stok operasional ditambahkan.');
    }

    public function damage(Request $request, OpsAsset $opsAsset, OpsAssetService $opsService)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $opsService->damage($opsAsset, (float) $validated['quantity'], $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kerusakan dicatat. Stok operasional dikurangi.');
    }
}
