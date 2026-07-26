<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Models\BusinessExpense;
use App\Models\PosOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessFundService
{
    /**
     * @return array{
     *     date: Carbon,
     *     opening: float,
     *     revenue: float,
     *     expense: float,
     *     closing: float,
     *     entries: \Illuminate\Database\Eloquent\Collection<int, BusinessExpense>
     * }
     */
    public function dayReport(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $opening = $this->balanceBefore($start);
        $revenue = $this->revenueBetween($start, $end);
        $entries = BusinessExpense::query()
            ->with('user:id,name')
            ->whereBetween('occurred_at', [$start, $end])
            ->latest('occurred_at')
            ->latest('id')
            ->get();
        $expense = round((float) $entries->sum('amount'), 4);

        return [
            'date' => $start,
            'opening' => $opening,
            'revenue' => $revenue,
            'expense' => $expense,
            'closing' => round($opening + $revenue - $expense, 4),
            'entries' => $entries,
        ];
    }

    public function balance(?Carbon $until = null): float
    {
        return $this->balanceAt($until ?? now());
    }

    public function balanceBefore(Carbon $date): float
    {
        return $this->balanceAt($date->copy()->startOfDay()->subMicrosecond());
    }

    public function addExpense(
        float $amount,
        string $category,
        PaymentMethod $paymentMethod,
        string $note,
        Carbon $occurredAt,
        ?User $user = null,
    ): BusinessExpense {
        return DB::transaction(function () use ($amount, $category, $paymentMethod, $note, $occurredAt, $user) {
            $this->validateExpense($amount, $category, $note, $occurredAt);

            return BusinessExpense::query()->create([
                'amount' => round($amount, 4),
                'category' => $category,
                'payment_method' => $paymentMethod,
                'note' => trim($note),
                'user_id' => $user?->id ?? auth()->id(),
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    public function updateExpense(
        BusinessExpense $expense,
        float $amount,
        string $category,
        PaymentMethod $paymentMethod,
        string $note,
        Carbon $occurredAt,
    ): BusinessExpense {
        return DB::transaction(function () use ($expense, $amount, $category, $paymentMethod, $note, $occurredAt) {
            $this->validateExpense($amount, $category, $note, $occurredAt, $expense->id);

            $expense->update([
                'amount' => round($amount, 4),
                'category' => $category,
                'payment_method' => $paymentMethod,
                'note' => trim($note),
                'occurred_at' => $occurredAt,
            ]);

            return $expense->fresh('user');
        });
    }

    private function validateExpense(
        float $amount,
        string $category,
        string $note,
        Carbon $occurredAt,
        ?int $excludeExpenseId = null,
    ): void {
        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengeluaran harus lebih dari 0.');
        }
        if (! array_key_exists($category, BusinessExpense::CATEGORIES)) {
            throw new RuntimeException('Kategori pengeluaran tidak valid.');
        }
        if (trim($note) === '') {
            throw new RuntimeException('Keterangan pengeluaran wajib diisi.');
        }

        $available = $this->balanceAt($occurredAt->copy()->endOfDay(), $excludeExpenseId);
        if ($amount > $available + 0.0001) {
            throw new RuntimeException(
                'Saldo dana usaha tidak cukup. Saldo tersedia pada tanggal tersebut Rp '.
                number_format($available, 0, ',', '.').'.'
            );
        }
    }

    private function balanceAt(Carbon $until, ?int $excludeExpenseId = null): float
    {
        $revenue = $this->revenueUntil($until);
        $expense = (float) BusinessExpense::query()
            ->where('occurred_at', '<=', $until)
            ->when($excludeExpenseId, fn ($query) => $query->where('id', '!=', $excludeExpenseId))
            ->sum('amount');

        return round($revenue - $expense, 4);
    }

    private function revenueUntil(Carbon $until): float
    {
        return round((float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $until)
            ->sum('total'), 4);
    }

    private function revenueBetween(Carbon $start, Carbon $end): float
    {
        return round((float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total'), 4);
    }
}
