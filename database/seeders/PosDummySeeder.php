<?php

namespace Database\Seeders;

use App\Enums\CostingMethod;
use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\ProductType;
use App\Models\InventoryLot;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosDummySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->cleanup();

            $kasir = User::where('email', 'kasir@local.test')->first();

            $tables = collect([
                ['table_number' => 'D01', 'label' => 'Meja Demo 1', 'barcode_token' => 'demo-token-meja-01'],
                ['table_number' => 'D02', 'label' => 'Meja Demo 2', 'barcode_token' => 'demo-token-meja-02'],
                ['table_number' => 'D03', 'label' => 'Meja Demo 3', 'barcode_token' => 'demo-token-meja-03'],
                ['table_number' => 'D04', 'label' => 'Meja Demo 4', 'barcode_token' => 'demo-token-meja-04'],
                ['table_number' => 'D05', 'label' => 'Meja Demo 5', 'barcode_token' => 'demo-token-meja-05'],
            ])->map(fn (array $row) => PosTable::create([...$row, 'is_active' => true]));

            $catalog = [
                ['FG-DEMO-001', 'Roti Tawar Classic', ProductType::FinishedGood, 'loaf', 12000, 18000],
                ['FG-DEMO-002', 'Roti Sobek', ProductType::FinishedGood, 'pack', 8000, 12000],
                ['FG-DEMO-003', 'Croissant Butter', ProductType::FinishedGood, 'pcs', 14000, 22000],
                ['FG-DEMO-004', 'Donat Coklat', ProductType::FinishedGood, 'pcs', 6000, 10000],
                ['FG-DEMO-005', 'Donat Keju', ProductType::FinishedGood, 'pcs', 6500, 11000],
                ['FG-DEMO-006', 'Brownies Coklat', ProductType::FinishedGood, 'slice', 9000, 15000],
                ['FG-DEMO-007', 'Kue Lapis Surabaya', ProductType::FinishedGood, 'slice', 11000, 18000],
                ['FG-DEMO-008', 'Muffin Blueberry', ProductType::FinishedGood, 'pcs', 7500, 13000],
                ['FG-DEMO-009', 'Bagel Original', ProductType::FinishedGood, 'pcs', 10000, 16000],
                ['FG-DEMO-010', 'Pain au Chocolat', ProductType::FinishedGood, 'pcs', 15000, 24000],
                ['FG-DEMO-011', 'Cinnamon Roll', ProductType::FinishedGood, 'pcs', 12000, 19000],
                ['FG-DEMO-012', 'Chiffon Pandan', ProductType::FinishedGood, 'slice', 8500, 14000],
                ['FG-DEMO-013', 'Wajik Ketan', ProductType::FinishedGood, 'pack', 7000, 12000],
                ['FG-DEMO-014', 'Risoles Mayo', ProductType::FinishedGood, 'pcs', 5000, 9000],
                ['FG-DEMO-015', 'Roti Garlic', ProductType::FinishedGood, 'pcs', 11000, 17000],
                ['SF-DEMO-001', 'Adonan Roti Putih', ProductType::SemiFinished, 'kg', 9000, 0],
                ['SF-DEMO-002', 'Adonan Pastry', ProductType::SemiFinished, 'kg', 16000, 0],
                ['SF-DEMO-003', 'Adonan Donat', ProductType::SemiFinished, 'kg', 7000, 0],
                ['SF-DEMO-004', 'Isian Keju Lembut', ProductType::SemiFinished, 'kg', 45000, 65000],
                ['SF-DEMO-005', 'Topping Coklat Cair', ProductType::SemiFinished, 'liter', 38000, 55000],
            ];

            $products = collect($catalog)->map(function (array $row) {
                [$sku, $name, $type, $unit, $cost, $price] = $row;

                $product = Product::create([
                    'sku' => $sku,
                    'name' => $name,
                    'type' => $type,
                    'unit' => $unit,
                    'standard_cost' => $cost,
                    'selling_price' => $price,
                    'image_path' => $this->demoImageForSku($sku),
                    'menu_category' => $this->demoCategoryForSku($sku),
                    'costing_method' => CostingMethod::WeightedAverage,
                    'is_active' => true,
                ]);

                InventoryLot::create([
                    'product_id' => $product->id,
                    'lot_number' => 'LOT-'.$sku,
                    'quantity_received' => 200,
                    'quantity_remaining' => 200,
                    'unit_cost' => $cost,
                    'received_at' => now(),
                ]);

                return $product;
            })->keyBy('sku');

            $p = fn (string $sku) => $products[$sku];

            $orders = [
                ['ORD-DEMO-001', null, PosOrderSource::Kasir, PosOrderStatus::Paid, 'Tanpa gula', 92000, PaymentMethod::Cash, now()->subDays(6), [
                    [$p('FG-DEMO-001'), 1, 18000],
                    [$p('FG-DEMO-003'), 2, 22000],
                    [$p('FG-DEMO-004'), 3, 10000],
                ]],
                ['ORD-DEMO-002', null, PosOrderSource::Kasir, PosOrderStatus::Paid, null, 50000, PaymentMethod::Qris, now()->subDays(5), [
                    [$p('FG-DEMO-010'), 1, 24000],
                    [$p('FG-DEMO-008'), 2, 13000],
                ]],
                ['ORD-DEMO-003', $tables[0], PosOrderSource::Online, PosOrderStatus::Paid, 'Kurang manis', 124000, PaymentMethod::Transfer, now()->subDays(4), [
                    [$p('FG-DEMO-002'), 3, 12000],
                    [$p('FG-DEMO-005'), 2, 11000],
                    [$p('FG-DEMO-011'), 2, 19000],
                    [$p('FG-DEMO-012'), 2, 14000],
                ]],
                ['ORD-DEMO-004', null, PosOrderSource::Kasir, PosOrderStatus::Open, 'Tambah plastik', 51000, null, now()->subHours(2), [
                    [$p('FG-DEMO-009'), 2, 16000],
                    [$p('FG-DEMO-011'), 1, 19000],
                ]],
                ['ORD-DEMO-005', $tables[1], PosOrderSource::Online, PosOrderStatus::Submitted, 'Meja depan', 60000, null, now()->subHour(), [
                    [$p('FG-DEMO-006'), 2, 15000],
                    [$p('FG-DEMO-007'), 1, 18000],
                    [$p('FG-DEMO-013'), 1, 12000],
                ]],
                ['ORD-DEMO-006', null, PosOrderSource::Kasir, PosOrderStatus::Paid, null, 17000, PaymentMethod::Cash, now()->subDays(3), [
                    [$p('FG-DEMO-015'), 1, 17000],
                ]],
                ['ORD-DEMO-007', null, PosOrderSource::Kasir, PosOrderStatus::Paid, 'Take away', 171000, PaymentMethod::Qris, now()->subDays(2), [
                    [$p('FG-DEMO-001'), 2, 18000],
                    [$p('FG-DEMO-004'), 2, 10000],
                    [$p('FG-DEMO-005'), 2, 11000],
                    [$p('FG-DEMO-014'), 3, 9000],
                    [$p('FG-DEMO-003'), 3, 22000],
                ]],
                ['ORD-DEMO-008', null, PosOrderSource::Kasir, PosOrderStatus::Cancelled, 'Pelanggan batal', 34000, null, now()->subDay(), [
                    [$p('FG-DEMO-004'), 2, 10000],
                    [$p('FG-DEMO-012'), 1, 14000],
                ]],
                ['ORD-DEMO-009', $tables[2], PosOrderSource::Online, PosOrderStatus::Open, null, 24000, null, now()->subMinutes(20), [
                    [$p('FG-DEMO-010'), 1, 24000],
                ]],
                ['ORD-DEMO-010', null, PosOrderSource::Kasir, PosOrderStatus::Paid, 'Kemasan hadiah', 107000, PaymentMethod::Cash, now()->subDay(), [
                    [$p('FG-DEMO-007'), 2, 18000],
                    [$p('FG-DEMO-008'), 2, 13000],
                    [$p('FG-DEMO-006'), 3, 15000],
                ]],
            ];

            foreach ($orders as [$number, $table, $source, $status, $note, $total, $payment, $at, $lines]) {
                $order = PosOrder::create([
                    'order_number' => $number,
                    'order_day' => $at->toDateString(),
                    'pos_table_id' => $table?->id,
                    'source' => $source,
                    'status' => $status,
                    'customer_note' => $note,
                    'subtotal' => $total,
                    'total' => $total,
                    'payment_method' => $payment,
                    'paid_at' => $status === PosOrderStatus::Paid ? $at : null,
                    'user_id' => $kasir?->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $index = 0;
                foreach ($lines as [$product, $qty, $price]) {
                    PosOrderItem::create([
                        'pos_order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'line_total' => round($qty * $price, 4),
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);

                    if ($status === PosOrderStatus::Paid) {
                        $index++;
                        SalesTransaction::create([
                            'pos_order_id' => $order->id,
                            'invoice_number' => 'POS-'.$number.'-'.$index,
                            'product_id' => $product->id,
                            'quantity' => $qty,
                            'selling_price' => $price,
                            'total_revenue' => round($qty * $price, 4),
                            'sold_at' => $at,
                            'created_at' => $at,
                            'updated_at' => $at,
                        ]);
                    }
                }
            }
        });
    }

    private function cleanup(): void
    {
        SalesTransaction::whereHas('posOrder', fn ($q) => $q->where('order_number', 'like', 'ORD-DEMO-%'))->delete();

        PosOrderItem::whereHas('order', fn ($q) => $q->where('order_number', 'like', 'ORD-DEMO-%'))->delete();
        PosOrder::where('order_number', 'like', 'ORD-DEMO-%')->delete();

        Product::where('sku', 'like', 'FG-DEMO-%')
            ->orWhere('sku', 'like', 'SF-DEMO-%')
            ->each(function (Product $product) {
                $product->inventoryLots()->delete();
                $product->delete();
            });

        PosTable::whereIn('table_number', ['D01', 'D02', 'D03', 'D04', 'D05'])->delete();
    }

    private function demoImageForSku(string $sku): string
    {
        return match ($sku) {
            'FG-DEMO-001', 'FG-DEMO-015' => 'images/products/bread-loaf.svg',
            'FG-DEMO-002', 'FG-DEMO-013' => 'images/products/bread-pack.svg',
            'FG-DEMO-003', 'FG-DEMO-010', 'FG-DEMO-011' => 'images/products/croissant.svg',
            'FG-DEMO-004', 'FG-DEMO-005' => 'images/products/donut.svg',
            'FG-DEMO-006', 'FG-DEMO-007', 'FG-DEMO-012' => 'images/products/cake-slice.svg',
            default => 'images/products/default-food.svg',
        };
    }

    private function demoCategoryForSku(string $sku): ?string
    {
        return match ($sku) {
            'FG-DEMO-003', 'FG-DEMO-010', 'FG-DEMO-011', 'FG-DEMO-004', 'FG-DEMO-005' => 'pastry',
            'FG-DEMO-006', 'FG-DEMO-007', 'FG-DEMO-012', 'FG-DEMO-013' => 'makanan',
            'FG-DEMO-014' => 'snack',
            'FG-DEMO-001', 'FG-DEMO-002', 'FG-DEMO-009', 'FG-DEMO-015' => 'makanan',
            default => 'lainnya',
        };
    }
}
