<?php

namespace App\Http\Controllers\Web;

use App\Enums\PosOrderStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\CogsCalculation;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Services\CogsCalculationService;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(CogsCalculationService $cogsService): View
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth()->endOfDay();

        return view('dashboard.index', [
            'today' => $this->salePeriodMetrics($todayStart, $todayEnd),
            'month' => $this->salePeriodMetrics($monthStart, $monthEnd),
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

        $omzet = round((float) $orders->sum('total'), 4);
        $omzetKotor = round((float) $orders->sum('subtotal'), 4);
        $diskonTotal = round((float) $orders->sum('discount_amount'), 4);
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
