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
        $metricSources = $subtractExpensesFromNet
            ? $this->metricSources($period, $rangeStart, $rangeEnd, $expenses, $count)
            : null;

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
            'metric_sources' => $metricSources,
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

    /** @return array{total: float, gaji: float, gaji_manual: float, lainnya: float, sources: array<string, mixed>} */
    private function expenseBreakdown(string $period, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $emptySources = [
            'gaji' => [],
            'gaji_manual' => [],
            'lainnya' => [],
            'lainnya_by_category' => [],
        ];
        $empty = [
            'total' => 0.0,
            'gaji' => 0.0,
            'gaji_manual' => 0.0,
            'lainnya' => 0.0,
            'sources' => $emptySources,
        ];

        if (! Schema::hasTable('business_expenses')) {
            $empty['gaji'] = $this->paidSalaryTotal($period, $rangeStart, $rangeEnd);
            $empty['sources']['gaji'] = $this->paidSalarySources($period, $rangeStart, $rangeEnd);

            return $empty;
        }

        $query = BusinessExpense::query();

        if ($period !== 'all') {
            $query->whereBetween('occurred_at', [$rangeStart, $rangeEnd]);
        }

        $entries = $query->orderByDesc('occurred_at')->orderByDesc('id')->get();
        $linkedExpenseIds = $this->linkedSalaryExpenseIds();
        $manualEntries = $entries
            ->where('category', 'gaji')
            ->reject(fn (BusinessExpense $entry) => in_array((int) $entry->id, $linkedExpenseIds, true));
        $otherEntries = $entries->filter(fn (BusinessExpense $entry) => $entry->category !== 'gaji');

        $gajiManual = round((float) $manualEntries->sum('amount'), 4);
        $lainnya = round((float) $otherEntries->sum('amount'), 4);
        $total = round((float) $entries->sum('amount'), 4);
        $gaji = $this->paidSalaryTotal($period, $rangeStart, $rangeEnd);

        $lainnyaByCategory = $otherEntries
            ->groupBy('category')
            ->map(function (Collection $group, string $category) {
                return [
                    'category' => $category,
                    'label' => BusinessExpense::CATEGORIES[$category] ?? ucfirst(str_replace('_', ' ', $category)),
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('amount'), 4),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'gaji' => $gaji,
            'gaji_manual' => $gajiManual,
            'lainnya' => $lainnya,
            'sources' => [
                'gaji' => $this->paidSalarySources($period, $rangeStart, $rangeEnd),
                'gaji_manual' => $this->mapExpenseSources($manualEntries, 'Dana Usaha · input manual'),
                'lainnya' => $this->mapExpenseSources($otherEntries, 'Dana Usaha'),
                'lainnya_by_category' => $lainnyaByCategory,
            ],
        ];
    }

    /**
     * @param  array{sources: array<string, mixed>}  $expenses
     * @return array<string, mixed>
     */
    private function metricSources(
        string $period,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $expenses,
        int $orderCount,
    ): array {
        return [
            'omzet_kotor' => [
                'module' => 'Modul Kasir',
                'detail' => "Subtotal {$orderCount} transaksi lunas (sebelum diskon)",
            ],
            'diskon' => [
                'module' => 'Modul Kasir',
                'detail' => 'Total diskon pada pesanan lunas',
            ],
            'lost_produk' => [
                'module' => 'Modul Kasir',
                'detail' => 'Barang lost / waste stok (rusak, gagal, dll.)',
                'items' => $this->lostProductSources($period, $rangeStart, $rangeEnd),
            ],
            'gaji' => [
                'module' => 'Modul Admin → Gaji Karyawan',
                'detail' => 'Gaji yang sudah dikonfirmasi bayar',
                'items' => $expenses['sources']['gaji'],
            ],
            'gaji_manual' => [
                'module' => 'Modul COGS → Dana Usaha',
                'detail' => 'Kategori gaji yang diinput manual (bukan dari konfirmasi gaji)',
                'items' => $expenses['sources']['gaji_manual'],
            ],
            'lainnya' => [
                'module' => 'Modul COGS → Dana Usaha',
                'detail' => 'Pengeluaran operasional, bahan, utilitas, dll.',
                'items' => $expenses['sources']['lainnya'],
                'by_category' => $expenses['sources']['lainnya_by_category'],
            ],
        ];
    }

    /** @return list<array{label: string, detail: string, amount: float, date: ?Carbon}> */
    private function paidSalarySources(string $period, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (! Schema::hasTable('employee_salaries')) {
            return [];
        }

        $query = EmployeeSalary::query()
            ->with('employee:id,name,employee_code')
            ->where('status', SalaryStatus::Paid);

        if ($period !== 'all') {
            $query->whereBetween('paid_at', [$rangeStart, $rangeEnd]);
        }

        return $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeSalary $salary) => [
                'label' => $salary->employee?->name ?? 'Karyawan',
                'detail' => trim(($salary->employee?->employee_code ? $salary->employee->employee_code.' · ' : '')
                    .$salary->periodLabel()),
                'amount' => (float) $salary->total,
                'date' => $salary->paid_at,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, detail: string, amount: float, date: ?Carbon}> */
    private function lostProductSources(string $period, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (! Schema::hasTable('stock_wastes')) {
            return [];
        }

        $query = StockWaste::query()->with('product:id,name,sku');

        if ($period !== 'all') {
            $query->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (StockWaste $waste) {
                $product = $waste->product?->name ?? 'Produk #'.$waste->product_id;

                return [
                    'label' => $product,
                    'detail' => trim($waste->reasonLabel().($waste->note ? ' · '.$waste->note : '')),
                    'amount' => (float) $waste->total_cost,
                    'date' => $waste->created_at,
                ];
            })
            ->values()
            ->all();
    }

    /** @param  Collection<int, BusinessExpense>  $entries
     * @return list<array{label: string, detail: string, amount: float, date: ?Carbon}>
     */
    private function mapExpenseSources(Collection $entries, string $modulePrefix): array
    {
        return $entries
            ->map(fn (BusinessExpense $expense) => [
                'label' => $expense->categoryLabel(),
                'detail' => trim($modulePrefix.($expense->note ? ' · '.$expense->note : '')),
                'amount' => (float) $expense->amount,
                'date' => $expense->occurred_at,
            ])
            ->values()
            ->all();
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
