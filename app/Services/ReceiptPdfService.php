<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;

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
            'url' => asset('storage/'.$path),
        ];
    }

    public function build(PosOrder $order): string
    {
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $pdf = new SimplePdf;

        $pdf->title($shopName);
        $pdf->line('Struk Pembayaran', 9, false, 'C');
        $pdf->spacer(4);
        $pdf->line($order->order_number, 9.5, true, 'C');
        $pdf->line($order->paid_at?->format('d/m/Y H:i') ?? '-', 8.5, false, 'C');

        if ($order->order_type) {
            $pdf->line($order->order_type->label(), 8.5, false, 'C');
        }

        if ($order->table) {
            $pdf->line('Meja: '.$order->table->label, 8.5, false, 'C');
        }

        if ($order->customer_note) {
            $pdf->line('Pelanggan: '.$order->customer_note, 8.5, false, 'C');
        }

        $pdf->spacer(4);
        $pdf->separator();

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $item->product?->name ?? 'Item';
            $pdf->twoColumns($name.' x '.$qty, Format::rupiah($item->line_total));

            if ($item->notes) {
                $pdf->line('  Catatan: '.$item->notes, 8, false, 'L');
            }
        }

        $pdf->separator();
        $pdf->twoColumns('TOTAL', Format::rupiah($order->total), 11);
        $pdf->line('Bayar: '.($order->payment_method?->label() ?? '-'), 8.5, false, 'L');

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $pdf->line('Diterima: '.Format::rupiah($order->amount_received), 8.5, false, 'L');
            $pdf->line('Kembalian: '.Format::rupiah($order->change_amount), 8.5, false, 'L');
        }

        if ($order->cashier?->name) {
            $pdf->line('Kasir: '.$order->cashier->name, 8.5, false, 'L');
        }

        $pdf->spacer(8);
        $pdf->line('Terima kasih', 9, false, 'C');

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
