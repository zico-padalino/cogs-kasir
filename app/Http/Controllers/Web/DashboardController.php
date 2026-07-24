<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CogsCalculationService;
use App\Support\Format;

class DashboardController extends Controller
{
    public function index(CogsCalculationService $cogsService)
    {
        return view('dashboard.index', [
            'summary' => $cogsService->getSummaryReport(),
            'format' => Format::class,
        ]);
    }
}
