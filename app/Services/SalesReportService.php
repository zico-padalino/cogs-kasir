<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Enums\SalaryStatus;
use App\Models\BusinessExpense;
use App\Models\EmployeeSalary;
use App\Models\PosOrder;
use App\Models\StockWaste;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SalesReportService
{
    /** @return array<string, mixed> */
    public function reportData(Request $request, string $defaultPeriod = 'day', bool $subtractExpensesFromNet = false): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:all,day,week,month'],
            'date' => ['nullable', 'date'],
            'week' => ['nullable', 'regex:/^\d{4}-W\d{2}$/'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $validated['period'] ?? $defaultPeriod;
        $range = $this->resolveRange($period, $validated);
        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];

        $ordersQuery = PosOrder::query()
            ->with(['table', 'cashier'])
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served]);

        if ($period !== 'all') {
            $ordersQuery->whereBetween('paid_at', [$rangeStart, $rangeEnd]);
        }

        $orders = $ordersQuery->orderByDesc('paid_at')->get();

        $omzetKotor = (float) $orders->sum('subtotal');
        $diskonTotal = (float) $orders->sum('discount_amount');
        $lostTotal = $this->lostProductTotal($period, $rangeStart, $rangeEnd);
        $expenses = $this->expenseBreakdown($period, $rangeStart, $rangeEnd);
        $expenseTotal = $expenses['total'];
        $expenseGaji = $expenses['gaji'];
        $expenseGajiManual = $expenses['gaji_manual'];
        $expenseLainnya = $expenses['lainnya'];
        $omzetPenjualan = round($omzetKotor - $diskonTotal - $lostTotal, 4);
        $omzet = $subtractExpensesFromNet
            ? round($omzetPenjualan - $expenseTotal, 4)
            : $omzetPenjualan;
        $count = $orders->count();

        $byPayment = [];
        foreach (PaymentMethod::cases() as $method) {
            $group = $orders->filter(fn (PosOrder $order) => $order->payment_method === $method);
            $byPayment[$method->value] = [
                'label' => $method->label(),
                'count' => $group->count(),
                'total' => (float) $group->sum('total'),
                'subtotal' => (float) $group->sum('subtotal'),
                'discount' => (float) $group->sum('discount_amount'),
            ];
        }

        $byDay = in_array($period, ['day', 'all'], true)
            ? collect()
            : $this->buildDailyBreakdown($orders, $rangeStart, $rangeEnd);

        return [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeLabel' => $this->rangeLabel($period, $rangeStart, $rangeEnd),
            'date' => $rangeStart,
            'orders' => $orders,
            'byDay' => $byDay,
            'omzet' => $omzet,
            'omzet_kotor' => $omzetKotor,
            'diskon_total' => $diskonTotal,
            'lost_total' => $lostTotal,
            'expense_total' => $expenseTotal,
            'expense_gaji' => $expenseGaji,
            'expense_gaji_manual' => $expenseGajiManual,
            'expense_lainnya' => $expenseLainnya,
            'subtract_expenses_from_net' => $subtractExpensesFromNet,
            'count' => $count,
            'average' => $count > 0 ? $omzet / $count : 0,
            'byPayment' => $byPayment,
            'format' => Format::class,
            'filters' => $this->filterValues($period, $rangeStart),
            'supportsAllPeriod' => $defaultPeriod === 'all',
        ];
    }

    private function lostProductTotal(string $period, Carbon $rangeStart, Carbon $rangeEnd): float
    {
        if (! Schema::hasTable('stock_wastes')) {
            return 0.0;
        }

        $query = StockWaste::query();

        if ($period !== 'all') {
            $query->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        }

        return round((float) $query->sum('total_cost'), 4);
    }

    /** @return array{total: float, gaji: float, gaji_manual: float, lainnya: float} */
    private function expenseBreakdown(string $period, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $empty = ['total' => 0.0, 'gaji' => 0.0, 'gaji_manual' => 0.0, 'lainnya' => 0.0];

        if (! Schema::hasTable('business_expenses')) {
            return $empty;
        }

        $query = BusinessExpense::query();

        if ($period !== 'all') {
            $query->whereBetween('occurred_at', [$rangeStart, $rangeEnd]);
        }

        $entries = $query->get(['id', 'amount', 'category']);
        $linkedExpenseIds = $this->linkedSalaryExpenseIds();
        $gajiManual = round((float) $entries
            ->where('category', 'gaji')
            ->reject(fn (BusinessExpense $entry) => in_array((int) $entry->id, $linkedExpenseIds, true))
            ->sum('amount'), 4);
        $lainnya = round((float) $entries
            ->filter(fn (BusinessExpense $entry) => $entry->category !== 'gaji')
            ->sum('amount'), 4);
        $total = round((float) $entries->sum('amount'), 4);
        $gaji = $this->paidSalaryTotal($period, $rangeStart, $rangeEnd);

        return [
            'total' => $total,
            'gaji' => $gaji,
            'gaji_manual' => $gajiManual,
            'lainnya' => $lainnya,
        ];
    }

    /** @return list<int> */
    private function linkedSalaryExpenseIds(): array
    {
        if (! Schema::hasTable('employee_salaries')
            || ! Schema::hasColumn('employee_salaries', 'business_expense_id')) {
            return [];
        }

        return EmployeeSalary::query()
            ->whereNotNull('business_expense_id')
            ->pluck('business_expense_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function paidSalaryTotal(string $period, Carbon $rangeStart, Carbon $rangeEnd): float
    {
        if (! Schema::hasTable('employee_salaries')) {
            return 0.0;
        }

        $query = EmployeeSalary::query()->where('status', SalaryStatus::Paid);

        if ($period !== 'all') {
            $query->whereBetween('paid_at', [$rangeStart, $rangeEnd]);
        }

        return round((float) $query->sum('total'), 4);
    }

    /** @param array<string, mixed> $validated */
    /** @return array{start: Carbon, end: Carbon} */
    private function resolveRange(string $period, array $validated): array
    {
        return match ($period) {
            'all' => [
                'start' => Carbon::create(2000, 1, 1)->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'week' => $this->weekRange($validated['week'] ?? null),
            'month' => $this->monthRange($validated['month'] ?? null),
            default => $this->dayRange($validated['date'] ?? null),
        };
    }

    /** @return array{start: Carbon, end: Carbon} */
    private function dayRange(?string $date): array
    {
        $start = Carbon::parse($date ?? now()->toDateString())->startOfDay();

        return [
            'start' => $start,
            'end' => $start->copy()->endOfDay(),
        ];
    }

    /** @return array{start: Carbon, end: Carbon} */
    private function weekRange(?string $week): array
    {
        $anchor = $week
            ? Carbon::parse($week)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        return [
            'start' => $anchor->copy()->startOfDay(),
            'end' => $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
        ];
    }

    /** @return array{start: Carbon, end: Carbon} */
    private function monthRange(?string $month): array
    {
        $anchor = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();

        return [
            'start' => $anchor->copy()->startOfDay(),
            'end' => $anchor->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /** @return Collection<int, array{date: Carbon, count: int, total: float}> */
    private function buildDailyBreakdown(Collection $orders, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $days = collect();
        $cursor = $rangeStart->copy()->startOfDay();

        while ($cursor->lte($rangeEnd)) {
            $dayOrders = $orders->filter(
                fn (PosOrder $order) => $order->paid_at && $order->paid_at->isSameDay($cursor)
            );

            $days->push([
                'date' => $cursor->copy(),
                'count' => $dayOrders->count(),
                'total' => (float) $dayOrders->sum('total'),
                'subtotal' => (float) $dayOrders->sum('subtotal'),
                'discount' => (float) $dayOrders->sum('discount_amount'),
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'all' => 'Keseluruhan',
            'week' => 'Mingguan',
            'month' => 'Bulanan',
            default => 'Harian',
        };
    }

    private function rangeLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'all' => 'Semua waktu',
            'week' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'month' => $start->translatedFormat('F Y'),
            default => $start->isToday()
                ? 'Hari ini'
                : $start->translatedFormat('d M Y'),
        };
    }

    /** @return array<string, string> */
    private function filterValues(string $period, Carbon $anchor): array
    {
        // Saat periode "all", range mulai dari tahun 2000 — jangan pakai itu
        // sebagai default tanggal/minggu/bulan di form filter.
        $picker = $period === 'all' ? now() : $anchor;

        return [
            'period' => $period,
            'date' => $picker->toDateString(),
            'week' => $picker->format('o-\WW'),
            'month' => $picker->format('Y-m'),
        ];
    }
}
