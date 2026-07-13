<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CashLedgerService;
use App\Support\Format;
use App\Support\KasirPin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class KasTunaiController extends Controller
{
    public function index(Request $request, CashLedgerService $cashLedger): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();
        $report = $cashLedger->dayReport($date);

        return view('kasir.kas-tunai.index', [
            ...$report,
            'balance' => $cashLedger->balance(),
            'format' => Format::class,
        ]);
    }

    public function storeFloat(Request $request, CashLedgerService $cashLedger)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            'amount.required' => 'Nominal setoran wajib diisi.',
            'note.required' => 'Keterangan setoran wajib diisi.',
        ]);

        try {
            $cashLedger->addFloatIn(
                (float) $validated['amount'],
                $validated['note'],
                KasirPin::operatorOrAuth(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Setoran kas berhasil dicatat.');
    }

    public function storeExpense(Request $request, CashLedgerService $cashLedger)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            'amount.required' => 'Nominal pengeluaran wajib diisi.',
            'note.required' => 'Keterangan pengeluaran wajib diisi (mis. beli gula darurat).',
        ]);

        try {
            $cashLedger->addExpense(
                (float) $validated['amount'],
                $validated['note'],
                KasirPin::operatorOrAuth(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Pengeluaran kas berhasil dicatat.');
    }
}
