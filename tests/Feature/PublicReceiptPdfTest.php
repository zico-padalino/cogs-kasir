<?php

namespace Tests\Feature;

use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Models\PosOrder;
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
}
