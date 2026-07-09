<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Models\PosOrder;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SalesReportService
{
    /** @return array<string, mixed> */
    public function reportData(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'week' => ['nullable', 'regex:/^\d{4}-W\d{2}$/'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $validated['period'] ?? 'day';
        $range = $this->resolveRange($period, $validated);
        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];

        $orders = PosOrder::query()
            ->with(['table', 'cashier'])
            ->where('status', PosOrderStatus::Paid)
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('paid_at')
            ->get();

        $omzet = (float) $orders->sum('total');
        $count = $orders->count();

        $byPayment = [];
        foreach (PaymentMethod::cases() as $method) {
            $group = $orders->filter(fn (PosOrder $order) => $order->payment_method === $method);
            $byPayment[$method->value] = [
                'label' => $method->label(),
                'count' => $group->count(),
                'total' => (float) $group->sum('total'),
            ];
        }

        $byDay = $period === 'day'
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
            'count' => $count,
            'average' => $count > 0 ? $omzet / $count : 0,
            'byPayment' => $byPayment,
            'format' => Format::class,
            'filters' => $this->filterValues($period, $rangeStart),
        ];
    }

    /** @param array<string, mixed> $validated */
    /** @return array{start: Carbon, end: Carbon} */
    private function resolveRange(string $period, array $validated): array
    {
        return match ($period) {
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
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'week' => 'Mingguan',
            'month' => 'Bulanan',
            default => 'Harian',
        };
    }

    private function rangeLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
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
        return [
            'period' => $period,
            'date' => $anchor->toDateString(),
            'week' => $anchor->format('o-\WW'),
            'month' => $anchor->format('Y-m'),
        ];
    }
}
