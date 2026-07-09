<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembukuanController extends Controller
{
    public function __construct(private SalesReportService $salesReport)
    {
    }

    public function index(Request $request): View
    {
        return view('kasir.pembukuan.index', $this->salesReport->reportData($request));
    }

    public function pdf(Request $request): View
    {
        return view('kasir.pembukuan.pdf', array_merge($this->salesReport->reportData($request), [
            'shopName' => config('pos.shop_name', 'Coffee & Kitchen'),
        ]));
    }
}
