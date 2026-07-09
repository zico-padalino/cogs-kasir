<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(private SalesReportService $salesReport)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.dashboard', $this->salesReport->reportData($request));
    }
}
