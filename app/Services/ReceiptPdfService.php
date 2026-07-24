<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ReceiptPdfService
{
    /**
     * @return array{binary: string, filename: string, path: string, url: string}
     */
    public function store(PosOrder $order): array
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $order->order_number) ?: 'struk';
        $filename = 'struk-'.$safe.'.pdf';
        $path = 'receipts/'.$filename;
        $binary = $this->build($order);

        Storage::disk('public')->put($path, $binary);

        return [
            'binary' => $binary,
            'filename' => $filename,
            'path' => $path,
            // Serve via signed Laravel route — direct /storage/... is often 403 on shared hosting.
            'url' => URL::signedRoute('receipts.public', ['order' => $order]),
        ];
    }

    public function build(PosOrder $order): string
    {
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $pdf = new SimplePdf;

        $pdf->title($shopName);
        $pdf->line('Struk Pembayaran', 12, false, 'C');
        $pdf->spacer(6);
        $pdf->line($order->order_number, 13, true, 'C');
        $pdf->line($order->paid_at?->format('d/m/Y H:i') ?? '-', 11, false, 'C');

        if ($order->order_type) {
            $pdf->line($order->order_type->label(), 11, false, 'C');
        }

        if ($order->table) {
            $pdf->line('Meja: '.$order->table->label, 11, false, 'C');
        }

        if ($order->customer_note) {
            $pdf->line('Pelanggan: '.$order->customer_note, 11, false, 'C');
        }

        $pdf->spacer(6);
        $pdf->separator();

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $item->product?->name ?? 'Item';
            $pdf->twoColumns($name.' x '.$qty, Format::rupiah($item->line_total), 12);

            if ($item->notes) {
                $pdf->line('  Catatan: '.$item->notes, 10, false, 'L');
            }
        }

        $pdf->separator();

        if ($order->hasDiscount()) {
            $pdf->twoColumns('Subtotal', Format::rupiah($order->subtotal), 12);
            $pdf->twoColumns('Diskon', '- '.Format::rupiah($order->discount_amount), 12);
        }

        $pdf->twoColumns('TOTAL', Format::rupiah($order->total), 15);
        $pdf->line('Bayar: '.($order->payment_method?->label() ?? '-'), 11, false, 'L');

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $pdf->line('Diterima: '.Format::rupiah($order->amount_received), 11, false, 'L');
            $pdf->line('Kembalian: '.Format::rupiah($order->change_amount), 11, false, 'L');
        }

        if ($order->cashierDisplayName() !== '-') {
            $pdf->line('Kasir: '.$order->cashierDisplayName(), 11, false, 'L');
        }

        $pdf->spacer(10);
        $pdf->line('Terima kasih', 12, true, 'C');

        return $pdf->render();
    }

    public function whatsappMessage(PosOrder $order, string $pdfUrl): string
    {
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');

        return implode("\n", [
            '*'.$shopName.'*',
            'Struk: '.$order->order_number,
            'Total: '.Format::rupiah($order->total),
            '',
            'PDF struk:',
            $pdfUrl,
        ]);
    }
}
