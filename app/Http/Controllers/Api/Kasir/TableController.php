<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\PosTable;
use App\Support\PosMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = PosTable::withCount([
            'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['open', 'submitted']),
        ])->orderBy('table_number')->get();

        return response()->json([
            'data' => [
                'tables' => $tables->map(fn (PosTable $table) => [
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                    'label' => $table->label,
                    'is_active' => (bool) ($table->is_active ?? true),
                    'open_orders_count' => (int) $table->open_orders_count,
                ])->values(),
                'order_url' => PosMenu::orderUrl(),
                'shop_name' => config('pos.shop_name'),
                'shop_title' => config('pos.shop_title'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'string', 'max:20', 'unique:pos_tables,table_number'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        $table = PosTable::create($validated);

        return response()->json([
            'message' => 'Meja berhasil ditambahkan.',
            'data' => [
                'id' => $table->id,
                'table_number' => $table->table_number,
                'label' => $table->label,
            ],
        ], 201);
    }

    public function barcode(): JsonResponse
    {
        return response()->json([
            'data' => [
                'order_url' => PosMenu::orderUrl(),
                'shop_name' => config('pos.shop_name'),
                'shop_title' => config('pos.shop_title'),
            ],
        ]);
    }
}
