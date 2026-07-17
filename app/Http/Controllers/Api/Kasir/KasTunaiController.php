<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Services\CashLedgerService;
use App\Support\KasirPin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class KasTunaiController extends Controller
{
    public function index(Request $request, CashLedgerService $cashLedger): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();
        $report = $cashLedger->dayReport($date);

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'opening' => (float) $report['opening'],
                'float_in' => (float) $report['floatIn'],
                'sale_in' => (float) $report['saleIn'],
                'change_out' => (float) $report['changeOut'],
                'expense' => (float) $report['expense'],
                'closing' => (float) $report['closing'],
                'balance' => $cashLedger->balance(),
                'entries' => $report['entries']->map(fn ($entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type?->value ?? $entry->type,
                    'direction' => $entry->direction?->value ?? $entry->direction,
                    'amount' => (float) $entry->amount,
                    'note' => $entry->note,
                    'occurred_at' => $entry->occurred_at?->toIso8601String(),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function storeFloat(Request $request, CashLedgerService $cashLedger): JsonResponse
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Setoran kas berhasil dicatat.',
            'data' => [
                'balance' => $cashLedger->balance(),
            ],
        ]);
    }

    public function storeExpense(Request $request, CashLedgerService $cashLedger): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            'amount.required' => 'Nominal pengeluaran wajib diisi.',
            'note.required' => 'Keterangan pengeluaran wajib diisi.',
        ]);

        try {
            $cashLedger->addExpense(
                (float) $validated['amount'],
                $validated['note'],
                KasirPin::operatorOrAuth(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pengeluaran kas berhasil dicatat.',
            'data' => [
                'balance' => $cashLedger->balance(),
            ],
        ]);
    }
}
