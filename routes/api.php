<?php

use App\Http\Controllers\Api\Auth\TokenAuthController;
use App\Http\Controllers\Api\CogsController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\Kasir\KasTunaiController;
use App\Http\Controllers\Api\Kasir\MenuCategoryController as KasirMenuCategoryController;
use App\Http\Controllers\Api\Kasir\OrderHistoryController;
use App\Http\Controllers\Api\Kasir\PembukuanController;
use App\Http\Controllers\Api\Kasir\PinController;
use App\Http\Controllers\Api\Kasir\PosController;
use App\Http\Controllers\Api\Kasir\ProductController as KasirProductController;
use App\Http\Controllers\Api\Kasir\TableController;
use App\Http\Controllers\Api\OverheadRateController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductionOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [TokenAuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [TokenAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [TokenAuthController::class, 'logout'])->name('auth.logout');

        Route::middleware(['role:kasir', 'api.attendance'])->prefix('kasir')->name('kasir.')->group(function () {
            Route::get('pin', [PinController::class, 'show'])->name('pin.show');
            Route::post('pin', [PinController::class, 'unlock'])->name('pin.unlock');
            Route::post('pin/lock', [PinController::class, 'lock'])->name('pin.lock');
            Route::get('pin/status', [PinController::class, 'status'])->name('pin.status');
            Route::get('pending-orders/poll', [PosController::class, 'pendingPoll'])->name('pending.poll');

            Route::middleware('kasir.pin')->group(function () {
                Route::get('pos', [PosController::class, 'index'])->name('pos');
                Route::post('orders/new', [PosController::class, 'newOrder'])->name('orders.new');
                Route::patch('orders/current', [PosController::class, 'updateOrder'])->name('orders.current');
                Route::patch('orders/discount', [PosController::class, 'updateDiscount'])->name('orders.discount');
                Route::post('orders/cancel', [PosController::class, 'cancelOrder'])->name('orders.cancel-active');
                Route::post('orders/{order}/load', [PosController::class, 'loadOrder'])->name('orders.load');
                Route::post('orders/{order}/confirm', [PosController::class, 'confirmOrder'])->name('orders.confirm');
                Route::post('orders/{order}/cancel', [PosController::class, 'cancelPendingOrder'])->name('orders.cancel');
                Route::post('items', [PosController::class, 'addItem'])->name('items.store');
                Route::patch('items/{item}', [PosController::class, 'updateItem'])->name('items.update');
                Route::delete('items/{item}', [PosController::class, 'removeItem'])->name('items.destroy');
                Route::post('pay', [PosController::class, 'pay'])->name('pay');
                Route::get('orders/{order}/receipt', [PosController::class, 'receipt'])->name('orders.receipt');

                Route::get('orders', [OrderHistoryController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [OrderHistoryController::class, 'show'])->name('orders.show');

                Route::get('tables', [TableController::class, 'index'])->name('tables.index');
                Route::post('tables', [TableController::class, 'store'])->name('tables.store');
                Route::get('barcode', [TableController::class, 'barcode'])->name('barcode');

                Route::get('products', [KasirProductController::class, 'index'])->name('products.index');
                Route::get('products/{product}', [KasirProductController::class, 'show'])->name('products.show');
                Route::put('products/{product}', [KasirProductController::class, 'update'])->name('products.update');

                Route::get('menu-categories', [KasirMenuCategoryController::class, 'index'])->name('menu-categories.index');
                Route::post('menu-categories', [KasirMenuCategoryController::class, 'store'])->name('menu-categories.store');
                Route::delete('menu-categories/{menuCategory}', [KasirMenuCategoryController::class, 'destroy'])->name('menu-categories.destroy');

                Route::get('pembukuan', [PembukuanController::class, 'index'])->name('pembukuan.index');

                Route::get('kas-tunai', [KasTunaiController::class, 'index'])->name('kas-tunai.index');
                Route::post('kas-tunai/float', [KasTunaiController::class, 'storeFloat'])->name('kas-tunai.float');
                Route::post('kas-tunai/expense', [KasTunaiController::class, 'storeExpense'])->name('kas-tunai.expense');
            });
        });
    });

    // Existing COGS API (unchanged)
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
