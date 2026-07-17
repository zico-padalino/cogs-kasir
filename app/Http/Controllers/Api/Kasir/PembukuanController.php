<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kasir\PosOrderResource;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembukuanController extends Controller
{
    public function __construct(private SalesReportService $salesReport) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->salesReport->reportData($request);

        return response()->json([
            'data' => [
                'period' => $data['period'],
                'period_label' => $data['periodLabel'],
                'range_label' => $data['rangeLabel'],
                'range_start' => $data['rangeStart']?->toIso8601String(),
                'range_end' => $data['rangeEnd']?->toIso8601String(),
                'omzet' => $data['omzet'],
                'count' => $data['count'],
                'average' => $data['average'],
                'by_payment' => $data['byPayment'],
                'by_day' => $data['byDay'],
                'filters' => $data['filters'],
                'orders' => PosOrderResource::collection($data['orders']),
            ],
        ]);
    }

    public function pdf(Request $request): JsonResponse
    {
        $data = $this->salesReport->reportData($request);

        return response()->json([
            'data' => [
                'shop_name' => config('pos.shop_name', 'Coffee & Kitchen'),
                'period' => $data['period'],
                'period_label' => $data['periodLabel'],
                'range_label' => $data['rangeLabel'],
                'omzet' => $data['omzet'],
                'count' => $data['count'],
                'average' => $data['average'],
                'by_payment' => $data['byPayment'],
                'orders' => PosOrderResource::collection($data['orders']),
            ],
        ]);
    }
}
