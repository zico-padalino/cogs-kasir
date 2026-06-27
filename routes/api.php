<?php

use App\Http\Controllers\Api\CogsController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OverheadRateController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductionOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/bom', [ProductController::class, 'storeBom']);

    Route::post('inventory/receive', [InventoryController::class, 'receive']);
    Route::get('inventory/{product}/stock', [InventoryController::class, 'stock']);

    Route::apiResource('production-orders', ProductionOrderController::class)->only(['index', 'store', 'show']);
    Route::post('production-orders/{production_order}/start', [ProductionOrderController::class, 'start']);
    Route::post('production-orders/{production_order}/complete', [ProductionOrderController::class, 'complete']);

    Route::post('cogs/calculate', [CogsController::class, 'calculate']);
    Route::get('cogs/products/{product}/roll-up', [CogsController::class, 'rollUp']);
    Route::get('cogs/history', [CogsController::class, 'history']);
    Route::get('cogs/summary', [CogsController::class, 'summary']);

    Route::apiResource('overhead-rates', OverheadRateController::class)->except(['show']);
});
