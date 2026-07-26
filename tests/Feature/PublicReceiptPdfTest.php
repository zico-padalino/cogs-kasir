<?php

namespace Tests\Feature;

use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Models\PosOrder;
use App\Models\Product;
use App\Services\ReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(): PosOrder
    {
        return PosOrder::create([
            'order_number' => 'TRX-20260723-017',
            'source' => PosOrderSource::Kasir,
            'order_type' => PosOrderType::Takeaway,
            'status' => PosOrderStatus::Paid,
            'subtotal' => 25000,
            'total' => 25000,
            'paid_at' => now(),
        ]);
    }

    public function test_signed_public_receipt_pdf_is_downloadable_without_login(): void
    {
        $order = $this->paidOrder();
        $url = URL::signedRoute('receipts.public', ['order' => $order]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unsigned_public_receipt_pdf_is_forbidden(): void
    {
        $order = $this->paidOrder();

        $this->get(route('receipts.public', $order))
            ->assertForbidden();
    }

    public function test_receipt_pdf_service_returns_signed_public_url_not_storage_path(): void
    {
        $order = $this->paidOrder();
        $pdf = app(ReceiptPdfService::class)->store($order);

        $this->assertStringContainsString('/struk/'.$order->id.'/pdf', $pdf['url']);
        $this->assertStringContainsString('signature=', $pdf['url']);
        $this->assertStringNotContainsString('/storage/receipts/', $pdf['url']);
    }

    public function test_kitchen_receipt_matches_order_header_and_only_contains_food_and_snacks(): void
    {
        config()->set('pos.kitchen_categories', ['makanan', 'snack']);

        $order = $this->paidOrder();
        $order->update(['customer_note' => 'Budi']);

        foreach ([
            ['sku' => 'FOOD-1', 'name' => 'Nasi Goreng', 'category' => 'makanan'],
            ['sku' => 'SNACK-1', 'name' => 'Kentang Goreng', 'category' => 'snack'],
            ['sku' => 'DRINK-1', 'name' => 'Es Kopi', 'category' => 'minuman'],
        ] as $index => $menu) {
            $product = Product::create([
                'sku' => $menu['sku'],
                'name' => $menu['name'],
                'type' => 'finished_good',
                'unit' => 'pcs',
                'standard_cost' => 5000,
                'selling_price' => 10000,
                'costing_method' => 'weighted_average',
                'menu_category' => $menu['category'],
                'is_active' => true,
                'is_menu_item' => true,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 10000,
                'line_total' => 10000,
                'notes' => $index === 0 ? 'Tidak pedas' : null,
            ]);
        }

        $pdf = app(ReceiptPdfService::class)->buildKitchen($order->fresh());

        $this->assertStringContainsString('Struk Dapur', $pdf);
        $this->assertStringContainsString($order->order_number, $pdf);
        $this->assertStringContainsString('Pelanggan: Budi', $pdf);
        $this->assertStringContainsString('Nasi Goreng', $pdf);
        $this->assertStringContainsString('Kentang Goreng', $pdf);
        $this->assertStringContainsString('Catatan: Tidak pedas', $pdf);
        $this->assertStringNotContainsString('Es Kopi', $pdf);
        $this->assertStringNotContainsString('TOTAL', $pdf);
    }
}
