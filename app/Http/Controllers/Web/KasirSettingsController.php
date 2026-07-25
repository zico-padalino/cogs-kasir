<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KasirSettingsController extends Controller
{
    public function edit(): View
    {
        $paper = ShopSettings::normalizeReceiptPaper(
            (string) ShopSettings::get('receipt_paper', config('pos.thermal.paper', '58mm'))
        );

        return view('kasir.settings', [
            'receiptPaper' => $paper,
            'options' => [
                '58mm' => [
                    'label' => '58mm',
                    'hint' => 'Lebar standar printer thermal kecil.',
                ],
                '80mm' => [
                    'label' => '80mm',
                    'hint' => 'Lebar printer thermal besar.',
                ],
                '58x210mm' => [
                    'label' => '58 × 210mm',
                    'hint' => 'Kertas gulung 58mm dengan tinggi halaman 210mm.',
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_paper' => ['required', 'in:58mm,80mm,58x210mm'],
        ], [
            'receipt_paper.required' => 'Pilih ukuran struk.',
            'receipt_paper.in' => 'Ukuran struk tidak valid.',
        ]);

        ShopSettings::put([
            'receipt_paper' => ShopSettings::normalizeReceiptPaper($validated['receipt_paper']),
        ]);

        return redirect()
            ->route('kasir.settings.edit')
            ->with('success', 'Ukuran struk disimpan.');
    }
}
