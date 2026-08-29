<?php

namespace App\Http\Controllers\Web;

use App\Enums\PosOrderStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\CogsCalculation;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StockWaste;
use App\Services\BusinessFundService;
use App\Services\CogsCalculationService;
use App\Services\InventoryCostService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        CogsCalculationService $cogsService,
        BusinessFundService $fundService,
        InventoryCostService $inventoryService,
    ): View
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth()->endOfDay();

        return view('dashboard.index', [
            'today' => $this->salePeriodMetrics($todayStart, $todayEnd),
            'month' => $this->salePeriodMetrics($monthStart, $monthEnd),
            'dailyRevenue' => $this->dailyRevenue(now()->subDays(6)->startOfDay(), $todayEnd),
            'materialStock' => $this->rawMaterialStock($inventoryService),
            'fundToday' => $fundService->dayReport($todayStart),
            'fundBalance' => $fundService->balance(),
            'expenseForecast' => $fundService->expenseForecast(),
            'snapshot' => $this->businessSnapshot(),
            'topMenus' => $this->topSellingMenus($monthStart, $monthEnd, 5),
            'summary' => $cogsService->getSummaryReport(),
            'format' => Format::class,
        ]);
    }

    /**
     * @return array{
     *     omzet: float,
     *     omzet_kotor: float,
     *     diskon_total: float,
     *     count: int,
     *     average: float,
     *     modal: float,
     *     laba: float,
     *     margin: float,
     *     label: string
     * }
     */
    private function salePeriodMetrics(Carbon $start, Carbon $end): array
    {
        $orders = PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'total', 'subtotal', 'discount_amount']);

        $omzetKotor = round((float) $orders->sum('subtotal'), 4);
        $diskonTotal = round((float) $orders->sum('discount_amount'), 4);
        $lostTotal = \
            Schema::hasTable('stock_wastes')
                ? round((float) StockWaste::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_cost'), 4)
                : 0.0;
        $omzet = round($omzetKotor - $diskonTotal - $lostTotal, 4);
        $count = $orders->count();

        $saleIds = SalesTransaction::query()
            ->whereBetween('sold_at', [$start, $end])
            ->pluck('id');

        $modal = 0.0;
        if ($saleIds->isNotEmpty()) {
            $modal = round((float) CogsCalculation::query()
                ->where('reference_type', SalesTransaction::class)
                ->whereIn('reference_id', $saleIds)
                ->get()
                ->sum(fn (CogsCalculation $calc) => $calc->totalHpp()), 4);
        }

        $laba = round($omzet - $modal, 4);
        $margin = $omzet > 0 ? round(($laba / $omzet) * 100, 1) : 0.0;

        return [
            'omzet' => $omzet,
            'omzet_kotor' => $omzetKotor,
            'diskon_total' => $diskonTotal,
            'count' => $count,
            'average' => $count > 0 ? round($omzet / $count, 4) : 0.0,
            'modal' => $modal,
            'laba' => $laba,
            'margin' => $margin,
            'label' => $start->isSameDay($end)
                ? ($start->isToday() ? 'Hari ini' : $start->translatedFormat('d M Y'))
                : $start->translatedFormat('F Y'),
        ];
    }

    /**
     * @return Collection<int, array{
     *     date: string,
     *     label: string,
     *     count: int,
     *     omzet_kotor: float,
     *     diskon_total: float,
     *     omzet: float
     * }>
     */
    private function dailyRevenue(Carbon $start, Carbon $end): Collection
    {
        $ordersByDate = PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereBetween('paid_at', [$start, $end])
            ->get(['paid_at', 'total', 'subtotal', 'discount_amount'])
            ->groupBy(fn (PosOrder $order) => $order->paid_at?->toDateString());

        $dayCount = (int) floor($start->diffInDays($end));

        return collect(range(0, $dayCount))
            ->map(function (int $offset) use ($start, $ordersByDate) {
                $date = $start->copy()->addDays($offset);
                $orders = $ordersByDate->get($date->toDateString(), collect());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->isToday() ? 'Hari ini' : $date->translatedFormat('d M Y'),
                    'count' => $orders->count(),
                    'omzet_kotor' => round((float) $orders->sum('subtotal'), 4),
                    'diskon_total' => round((float) $orders->sum('discount_amount'), 4),
                    'omzet' => round((float) $orders->sum('total'), 4),
                ];
            })
            ->reverse()
            ->values();
    }

    /**
     * @return array{
     *     menu_aktif: int,
     *     bahan_baku: int,
     *     bahan_jadi: int,
     *     menu_tanpa_harga: int,
     *     menu_tanpa_hpp: int
     * }
     */
    private function businessSnapshot(): array
    {
        $menus = Product::query()->sellable()->get(['id', 'selling_price', 'unit_hpp', 'standard_cost']);

        return [
            'menu_aktif' => $menus->count(),
            'bahan_baku' => Product::query()
                ->where('type', ProductType::RawMaterial)
                ->where('is_active', true)
                ->count(),
            'bahan_jadi' => Product::query()
                ->where('type', ProductType::SemiFinished)
                ->where('is_active', true)
                ->count(),
            'menu_tanpa_harga' => $menus->filter(fn (Product $p) => (float) $p->selling_price <= 0)->count(),
            'menu_tanpa_hpp' => $menus->filter(fn (Product $p) => $p->effectiveUnitHpp() <= 0)->count(),
        ];
    }

    /**
     * @return array{
     *     items: Collection<int, array{id: int, name: string, unit: string, qty: float, avg_cost: float, value: float}>,
     *     count: int,
     *     in_stock: int,
     *     empty: int,
     *     total_value: float,
     *     qty_by_unit: array<string, float>
     * }
     */
    private function rawMaterialStock(InventoryCostService $inventoryService): array
    {
        $items = Product::query()
            ->where('type', ProductType::RawMaterial)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($inventoryService) {
                $qty = $product->availableQuantity();
                $avgCost = $inventoryService->getWeightedAverageCost($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit' => $product->unit ?: 'unit',
                    'qty' => $qty,
                    'avg_cost' => $avgCost,
                    'value' => round($qty * $avgCost, 4),
                ];
            })
            ->sortByDesc('value')
            ->values();

        $qtyByUnit = $items
            ->filter(fn (array $row) => $row['qty'] > 0)
            ->groupBy('unit')
            ->map(fn (Collection $group) => round((float) $group->sum('qty'), 4))
            ->all();

        return [
            'items' => $items,
            'count' => $items->count(),
            'in_stock' => $items->filter(fn (array $row) => $row['qty'] > 0)->count(),
            'empty' => $items->filter(fn (array $row) => $row['qty'] <= 0)->count(),
            'total_value' => round((float) $items->sum('value'), 4),
            'qty_by_unit' => $qtyByUnit,
        ];
    }

    /**
     * @return Collection<int, array{name: string, quantity: float, revenue: float}>
     */
    private function topSellingMenus(Carbon $start, Carbon $end, int $limit = 5): Collection
    {
        return SalesTransaction::query()
            ->select([
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_revenue) as total_revenue'),
            ])
            ->whereBetween('sold_at', [$start, $end])
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->with('product:id,name')
            ->get()
            ->map(fn (SalesTransaction $row) => [
                'name' => $row->product?->name ?? 'Menu dihapus',
                'quantity' => round((float) $row->total_quantity, 2),
                'revenue' => round((float) $row->total_revenue, 4),
            ]);
    }
}
