<?php

namespace App\Services;

use App\Enums\CashLedgerType;
use App\Models\CashLedgerEntry;
use App\Models\PosOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashLedgerService
{
    public function balance(?Carbon $until = null): float
    {
        $query = CashLedgerEntry::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as balance");

        if ($until) {
            $query->where('occurred_at', '<=', $until->copy()->endOfDay());
        }

        return (float) $query->value('balance');
    }

    public function balanceBefore(Carbon $date): float
    {
        return (float) CashLedgerEntry::query()
            ->where('occurred_at', '<', $date->copy()->startOfDay())
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');
    }

    /**
     * @return array{
     *     date: Carbon,
     *     opening: float,
     *     floatIn: float,
     *     saleIn: float,
     *     changeOut: float,
     *     expense: float,
     *     closing: float,
     *     entries: Collection<int, CashLedgerEntry>
     * }
     */
    public function dayReport(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $entries = CashLedgerEntry::query()
            ->with(['user', 'order'])
            ->whereBetween('occurred_at', [$start, $end])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $sumType = fn (CashLedgerType $type): float => (float) $entries
            ->filter(fn (CashLedgerEntry $entry) => $entry->type === $type)
            ->sum(fn (CashLedgerEntry $entry) => (float) $entry->amount);

        $opening = $this->balanceBefore($date);
        $floatIn = $sumType(CashLedgerType::FloatIn);
        $saleIn = $sumType(CashLedgerType::SaleIn);
        $changeOut = $sumType(CashLedgerType::ChangeOut);
        $expense = $sumType(CashLedgerType::Expense);
        $netDay = (float) $entries->sum(fn (CashLedgerEntry $entry) => $entry->signedAmount());

        return [
            'date' => $date->copy()->startOfDay(),
            'opening' => $opening,
            'floatIn' => $floatIn,
            'saleIn' => $saleIn,
            'changeOut' => $changeOut,
            'expense' => $expense,
            'closing' => $opening + $netDay,
            'entries' => $entries,
        ];
    }

    public function addFloatIn(float $amount, string $note, ?User $user = null, ?Carbon $at = null): CashLedgerEntry
    {
        return $this->addManual(CashLedgerType::FloatIn, $amount, $note, $user, $at);
    }

    public function addExpense(float $amount, string $note, ?User $user = null, ?Carbon $at = null): CashLedgerEntry
    {
        return $this->addManual(CashLedgerType::Expense, $amount, $note, $user, $at);
    }

    public function recordCashSale(PosOrder $order, ?User $cashier = null): void
    {
        $saleAmount = round((float) $order->total, 4);
        $changeAmount = round((float) ($order->change_amount ?? 0), 4);
        $occurredAt = $order->paid_at ?? now();

        if ($saleAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $cashier, $saleAmount, $changeAmount, $occurredAt) {
            CashLedgerEntry::query()
                ->where('pos_order_id', $order->id)
                ->whereIn('type', [CashLedgerType::SaleIn->value, CashLedgerType::ChangeOut->value])
                ->delete();

            $this->createEntry(
                CashLedgerType::SaleIn,
                $saleAmount,
                'Penjualan '.$order->order_number,
                $cashier?->id ?? $order->user_id,
                $occurredAt,
                $order->id,
            );

            if ($changeAmount > 0) {
                $this->createEntry(
                    CashLedgerType::ChangeOut,
                    $changeAmount,
                    'Kembalian '.$order->order_number,
                    $cashier?->id ?? $order->user_id,
                    $occurredAt,
                    $order->id,
                );
            }
        });
    }

    private function addManual(
        CashLedgerType $type,
        float $amount,
        string $note,
        ?User $user = null,
        ?Carbon $at = null,
    ): CashLedgerEntry {
        if (! $type->isManual()) {
            throw new RuntimeException('Tipe kas ini hanya dicatat otomatis.');
        }

        $amount = round($amount, 4);
        if ($amount <= 0) {
            throw new RuntimeException('Nominal harus lebih dari 0.');
        }

        $note = trim($note);
        if ($note === '') {
            throw new RuntimeException('Keterangan wajib diisi.');
        }

        if ($type === CashLedgerType::Expense) {
            $available = $this->balance();
            if ($amount > $available + 0.0001) {
                throw new RuntimeException('Saldo kas tidak cukup. Saldo saat ini Rp '.number_format($available, 0, ',', '.').'.');
            }
        }

        return $this->createEntry($type, $amount, $note, $user?->id, $at ?? now());
    }

    private function createEntry(
        CashLedgerType $type,
        float $amount,
        string $note,
        ?int $userId = null,
        ?Carbon $at = null,
        ?int $orderId = null,
    ): CashLedgerEntry {
        return CashLedgerEntry::create([
            'type' => $type,
            'direction' => $type->direction(),
            'amount' => $amount,
            'note' => $note,
            'pos_order_id' => $orderId,
            'user_id' => $userId,
            'occurred_at' => $at ?? now(),
        ]);
    }
}
