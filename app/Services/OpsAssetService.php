<?php

namespace App\Services;

use App\Models\OpsAsset;
use App\Models\OpsAssetLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpsAssetService
{
    public function create(string $name, string $unit = 'pcs', float $initialQty = 0, ?string $note = null): OpsAsset
    {
        return DB::transaction(function () use ($name, $unit, $initialQty, $note) {
            $asset = OpsAsset::query()->create([
                'name' => trim($name),
                'unit' => $unit ?: 'pcs',
                'quantity_on_hand' => max(0, $initialQty),
                'is_active' => true,
                'note' => $note,
            ]);

            if ($initialQty > 0) {
                $this->writeLog($asset, 'receive', $initialQty, 0, (float) $asset->quantity_on_hand, 'Stok awal');
            }

            return $asset;
        });
    }

    public function receive(OpsAsset $asset, float $quantity, ?string $note = null, ?User $user = null): OpsAssetLog
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Jumlah masuk harus lebih dari 0.');
        }

        return DB::transaction(function () use ($asset, $quantity, $note, $user) {
            $asset->refresh();
            $before = (float) $asset->quantity_on_hand;
            $after = round($before + $quantity, 6);
            $asset->update(['quantity_on_hand' => $after]);

            return $this->writeLog($asset, 'receive', $quantity, $before, $after, $note, $user);
        });
    }

    public function damage(OpsAsset $asset, float $quantity, ?string $note = null, ?User $user = null): OpsAssetLog
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Jumlah rusak harus lebih dari 0.');
        }

        return DB::transaction(function () use ($asset, $quantity, $note, $user) {
            $asset->refresh();
            $before = (float) $asset->quantity_on_hand;

            if ($before < $quantity) {
                throw new RuntimeException("Stok {$asset->name} tidak cukup (tersedia {$before} {$asset->unit}).");
            }

            $after = round($before - $quantity, 6);
            $asset->update(['quantity_on_hand' => $after]);

            return $this->writeLog($asset, 'damage', $quantity, $before, $after, $note, $user);
        });
    }

    private function writeLog(
        OpsAsset $asset,
        string $action,
        float $quantity,
        float $before,
        float $after,
        ?string $note = null,
        ?User $user = null,
    ): OpsAssetLog {
        return OpsAssetLog::query()->create([
            'ops_asset_id' => $asset->id,
            'action' => $action,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'note' => $note,
            'user_id' => $user?->id ?? auth()->id(),
        ]);
    }
}
