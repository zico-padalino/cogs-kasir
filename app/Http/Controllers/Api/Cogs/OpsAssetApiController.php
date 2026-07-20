<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Http\Controllers\Controller;
use App\Models\OpsAsset;
use App\Models\OpsAssetLog;
use App\Services\OpsAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsAssetApiController extends Controller
{
    public function index(): JsonResponse
    {
        $assets = OpsAsset::query()->where('is_active', true)->orderBy('name')->get();
        $logs = OpsAssetLog::query()->with('asset:id,name,unit')->latest()->limit(100)->get();

        return response()->json([
            'data' => [
                'assets' => $assets->map(fn (OpsAsset $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'unit' => $a->unit,
                    'quantity_on_hand' => (float) $a->quantity_on_hand,
                    'note' => $a->note,
                ])->values(),
                'logs' => $logs->map(fn (OpsAssetLog $log) => [
                    'id' => $log->id,
                    'ops_asset_id' => $log->ops_asset_id,
                    'asset_name' => $log->asset?->name,
                    'action' => $log->action,
                    'action_label' => $log->actionLabel(),
                    'quantity' => (float) $log->quantity,
                    'quantity_after' => (float) $log->quantity_after,
                    'note' => $log->note,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, OpsAssetService $opsService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $opsService->create(
            name: $validated['name'],
            unit: $validated['unit'] ?? 'pcs',
            initialQty: (float) ($validated['quantity'] ?? 0),
            note: $validated['note'] ?? null,
        );

        return response()->json([
            'message' => 'Item ditambahkan.',
            'data' => $asset,
        ], 201);
    }

    public function receive(Request $request, OpsAsset $opsAsset, OpsAssetService $opsService): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $log = $opsService->receive($opsAsset, (float) $validated['quantity'], $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Stok ditambahkan.',
            'data' => [
                'log_id' => $log->id,
                'quantity_on_hand' => (float) $opsAsset->fresh()->quantity_on_hand,
            ],
        ]);
    }

    public function damage(Request $request, OpsAsset $opsAsset, OpsAssetService $opsService): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $log = $opsService->damage($opsAsset, (float) $validated['quantity'], $validated['note'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Kerusakan dicatat.',
            'data' => [
                'log_id' => $log->id,
                'quantity_on_hand' => (float) $opsAsset->fresh()->quantity_on_hand,
            ],
        ]);
    }
}
