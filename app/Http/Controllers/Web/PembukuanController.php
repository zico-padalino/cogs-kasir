<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembukuanController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();

        $orders = PosOrder::query()
            ->with(['table', 'cashier'])
            ->where('status', PosOrderStatus::Paid)
            ->whereDate('paid_at', $date)
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

        return view('kasir.pembukuan.index', [
            'date' => $date,
            'orders' => $orders,
            'omzet' => $omzet,
            'count' => $count,
            'average' => $count > 0 ? $omzet / $count : 0,
            'byPayment' => $byPayment,
            'format' => Format::class,
        ]);
    }
}
