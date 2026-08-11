<?php

namespace App\Console\Commands;

use App\Models\InventoryReservation;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\SalesTransaction;
use App\Models\StockWaste;
use App\Services\CashLedgerService;
use App\Services\CogsCalculationService;
use App\Support\KitchenBoardCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VoidPosOrderCommand extends Command
{
    protected $signature = 'pos:void-order
        {order_number : Nomor pesanan, mis. TRX-20260726-002}
        {--force : Lewati konfirmasi}';

    protected $description = 'Hapus pesanan POS dan kembalikan stok (batal COGS + kas)';

    public function handle(
        CogsCalculationService $cogsCalculationService,
        CashLedgerService $cashLedgerService,
    ): int {
        $orderNumber = trim((string) $this->argument('order_number'));

        $order = PosOrder::query()
            ->with(['items.product', 'salesTransactions', 'table'])
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            $this->error("Pesanan {$orderNumber} tidak ditemukan.");

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Nilai'],
            [
                ['ID', $order->id],
                ['Nomor', $order->order_number],
                ['Pelanggan', $order->customer_note ?: '-'],
                ['Status', $order->status?->value ?? '-'],
                ['Total', $order->total],
                ['Item', $order->items->count()],
                ['Sales COGS', $order->salesTransactions->count()],
            ],
        );

        foreach ($order->items as $item) {
            $this->line(sprintf(
                '  - %s × %s',
                $item->product?->name ?? 'Item#'.$item->product_id,
                rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') ?: '0',
            ));
        }

        if (! $this->option('force') && ! $this->confirm("Hapus {$orderNumber} dan kembalikan stok?", false)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($order, $cogsCalculationService, $cashLedgerService) {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->load(['salesTransactions']);

            foreach ($order->salesTransactions as $sale) {
                $cogsCalculationService->reverseSaleCogs($sale);
                $sale->delete();
            }

            $cashLedgerService->clearOrderSaleEntries($order);

            if (Schema::hasTable('inventory_reservations')) {
                InventoryReservation::query()->where('pos_order_id', $order->id)->delete();
            }

            if (Schema::hasTable('stock_wastes')) {
                StockWaste::query()->where('pos_order_id', $order->id)->update(['pos_order_id' => null]);
            }

            $proofPath = $order->payment_proof_path;
            PosOrderItem::query()->where('pos_order_id', $order->id)->delete();
            SalesTransaction::query()->where('pos_order_id', $order->id)->delete();
            $order->delete();

            if ($proofPath) {
                if (str_starts_with($proofPath, 'uploads/')) {
                    $full = public_path($proofPath);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                } else {
                    Storage::disk('public')->delete($proofPath);
                }
            }
        });

        KitchenBoardCache::forget();

        $this->info("Selesai: {$orderNumber} dihapus, stok dikembalikan.");

        return self::SUCCESS;
    }
}
