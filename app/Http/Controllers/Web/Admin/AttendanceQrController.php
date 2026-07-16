<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Support\ShopSettings;
use Illuminate\View\View;

class AttendanceQrController extends Controller
{
    public function show(AttendanceService $attendanceService): View
    {
        return view('admin.attendances.qr', [
            'shopName' => ShopSettings::get('shop_name', config('pos.shop_name')),
            'shopTitle' => ShopSettings::get('shop_title', config('pos.shop_title')),
            'scanUrl' => $attendanceService->publicScanUrl(),
            'settings' => $attendanceService->settings(),
        ]);
    }
}
