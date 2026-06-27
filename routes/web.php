<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\CogsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\KasirController;
use App\Http\Controllers\Web\OverheadRateController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductionOrderController;
use App\Http\Controllers\Web\ResetDataController;
use Illuminate\Support\Facades\Route;

Route::redirect('/api', '/login');
Route::redirect('/api/v1', '/login');
Route::redirect('/api/v1/products', '/login');
Route::redirect('/api/v1/inventory/receive', '/login');
Route::redirect('/api/v1/production-orders', '/login');
Route::redirect('/api/v1/cogs/calculate', '/login');
Route::redirect('/api/v1/cogs/history', '/login');
Route::redirect('/api/v1/overhead-rates', '/login');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:kasir'])->group(function () {
    Route::get('kasir', [KasirController::class, 'index'])->name('kasir.index');
});

Route::middleware(['auth', 'role:cogs'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('reset-data', [ResetDataController::class, 'show'])->name('reset-data.show');
    Route::post('reset-data', [ResetDataController::class, 'reset'])->name('reset-data.store');

    Route::resource('products', ProductController::class);
    Route::post('products/{product}/bom', [ProductController::class, 'storeBom'])->name('products.bom.store');
    Route::put('products/{product}/bom/{bom}', [ProductController::class, 'updateBom'])->name('products.bom.update');
    Route::delete('products/{product}/bom/{bom}', [ProductController::class, 'destroyBom'])->name('products.bom.destroy');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/receive', [InventoryController::class, 'receive'])->name('inventory.receive');
    Route::put('inventory/lots/{lot}', [InventoryController::class, 'update'])->name('inventory.lots.update');
    Route::delete('inventory/lots/{lot}', [InventoryController::class, 'destroy'])->name('inventory.lots.destroy');

    Route::resource('production-orders', ProductionOrderController::class);
    Route::post('production-orders/{production_order}/start', [ProductionOrderController::class, 'start'])->name('production-orders.start');
    Route::post('production-orders/{production_order}/complete', [ProductionOrderController::class, 'complete'])->name('production-orders.complete');

    Route::get('cogs/calculate', [CogsController::class, 'calculate'])->name('cogs.calculate');
    Route::post('cogs/calculate', [CogsController::class, 'process'])->name('cogs.process');
    Route::get('cogs/history', [CogsController::class, 'history'])->name('cogs.history');
    Route::get('cogs/history/{calculation}', [CogsController::class, 'show'])->name('cogs.history.show');
    Route::delete('cogs/history/{calculation}', [CogsController::class, 'destroy'])->name('cogs.history.destroy');

    Route::resource('overhead-rates', OverheadRateController::class)->except(['show']);
});
