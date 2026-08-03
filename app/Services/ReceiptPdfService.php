<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\PosItemNotes;
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
        $pdf = new SimplePdf;

        $this->appendReceiptHeader($pdf, $order, 'Struk Pembayaran');

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $item->product?->name ?? 'Item';
            $pdf->twoColumns($name.' x '.$qty, Format::rupiah($item->line_total), 28);
            $this->appendItemNotes($pdf, $item->notes);
        }

        $pdf->separator();

        if ($order->hasDiscount()) {
            $pdf->twoColumns('Subtotal', Format::rupiah($order->subtotal), 28);
            $pdf->twoColumns('Diskon', '- '.Format::rupiah($order->discount_amount), 28);
        }

        $pdf->twoColumns('TOTAL', Format::rupiah($order->total), 34);
        $pdf->line('Bayar: '.($order->payment_method?->label() ?? '-'), 26, false, 'L');

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $pdf->line('Diterima: '.Format::rupiah($order->amount_received), 26, false, 'L');
            $pdf->line('Kembalian: '.Format::rupiah($order->change_amount), 26, false, 'L');
        }

        if ($order->cashierDisplayName() !== '-') {
            $pdf->line('Kasir: '.$order->cashierDisplayName(), 26, false, 'L');
        }

        $pdf->spacer(16);
        $pdf->line('Terima kasih', 28, true, 'C');

        return $pdf->render();
    }

    /**
     * @return array{binary: string, filename: string, path: string, url: string}
     */
    public function storeKitchen(PosOrder $order): array
    {
        return $this->storeStationTicket($order, 'kitchen');
    }

    /**
     * @return array{binary: string, filename: string, path: string, url: string}
     */
    public function storeBar(PosOrder $order): array
    {
        return $this->storeStationTicket($order, 'bar');
    }

    /**
     * @param  'kitchen'|'bar'  $station
     * @return array{binary: string, filename: string, path: string, url: string}
     */
    public function storeStationTicket(PosOrder $order, string $station): array
    {
        $posService = app(PosOrderService::class);
        $ticket = $posService->orderForStation($order, $station);

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $order->order_number) ?: 'struk';
        $prefix = $station === 'bar' ? 'bar' : 'dapur';
        $filename = $prefix.'-'.$safe.'.pdf';
        $path = 'receipts/'.$filename;
        $binary = $this->buildStationTicket($ticket, $station);

        Storage::disk('public')->put($path, $binary);

        $route = $station === 'bar' ? 'receipts.bar' : 'receipts.kitchen';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'path' => $path,
            'url' => URL::signedRoute($route, ['order' => $order]),
        ];
    }

    public function buildKitchen(PosOrder $order): string
    {
        return $this->buildStationTicket(
            app(PosOrderService::class)->orderForStation($order, 'kitchen'),
            'kitchen',
        );
    }

    public function buildBar(PosOrder $order): string
    {
        return $this->buildStationTicket(
            app(PosOrderService::class)->orderForStation($order, 'bar'),
            'bar',
        );
    }

    /**
     * @param  'kitchen'|'bar'  $station
     */
    public function buildStationTicket(PosOrder $order, string $station): string
    {
        $pdf = new SimplePdf;
        $title = $station === 'bar' ? 'Struk Bar' : 'Struk Dapur';
        $emptyLabel = $station === 'bar' ? 'Tidak ada item minuman' : 'Tidak ada item dapur';

        $this->appendReceiptHeader($pdf, $order, $title);

        if ($order->items->isEmpty()) {
            $pdf->line($emptyLabel, 26, false, 'C');
        } else {
            foreach ($order->items as $item) {
                $qty = Format::number($item->quantity, 0);
                $name = $item->product?->name ?? 'Item';
                $pdf->checkItem($name.' x '.$qty, 28);
                $this->appendItemNotes($pdf, $item->notes);
            }
        }

        $pdf->separator();

        if ($order->cashierDisplayName() !== '-') {
            $pdf->line('Kasir: '.$order->cashierDisplayName(), 26, false, 'L');
        }

        $pdf->spacer(16);
        $pdf->line('Ceklis item yang sudah selesai', 26, false, 'C');

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

    /** Header tunggal agar struk pelanggan dan tiket stasiun selalu konsisten. */
    private function appendReceiptHeader(SimplePdf $pdf, PosOrder $order, string $title): void
    {
        $pdf->title((string) config('pos.shop_name', 'Coffee & Kitchen'));
        $pdf->line($title, 28, false, 'C');
        $pdf->spacer(12);
        $pdf->line($order->order_number, 30, true, 'C');
        $pdf->line(
            $order->paid_at?->format('d/m/Y H:i')
                ?? $order->updated_at?->format('d/m/Y H:i')
                ?? now()->format('d/m/Y H:i'),
            26,
            false,
            'C',
        );
        if ($order->isOpenBill()) {
            $pdf->line('TAGIHAN TERBUKA', 26, true, 'C');
        }

        if ($order->order_type) {
            $pdf->line($order->order_type->label(), 26, false, 'C');
        }

        if ($order->table) {
            $pdf->line('Meja: '.$order->table->label, 26, false, 'C');
        }

        if ($order->customer_note) {
            $pdf->line('Pelanggan: '.$order->customer_note, 26, false, 'C');
        }

        $pdf->spacer(12);
        $pdf->separator();
    }

    /** Pecah catatan pelanggan & add-on agar karakter · tidak jadi "?" di PDF. */
    private function appendItemNotes(SimplePdf $pdf, ?string $notes): void
    {
        $parts = PosItemNotes::split($notes);

        foreach ($parts['addon_labels'] as $label) {
            $pdf->line('  '.$label, 24, false, 'L');
        }

        if ($parts['customer']) {
            $pdf->line('  Catatan: '.$parts['customer'], 24, false, 'L');
        }
    }
}
