<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function __construct(private SalesReportService $salesReport) {}

    public function index(Request $request): JsonResponse
    {
        $report = $this->salesReport->reportData($request, defaultPeriod: 'all');

        return response()->json([
            'message' => 'Dashboard admin berhasil dimuat.',
            'data' => $report,
        ]);
    }
}
