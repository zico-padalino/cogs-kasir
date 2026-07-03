<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\CogsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\KasirController;
use App\Http\Controllers\Web\KasirProductController;
use App\Http\Controllers\Web\OverheadRateController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductionOrderController;
use App\Http\Controllers\Web\PwaController;
use App\Http\Controllers\Web\ResetDataController;
use App\Http\Controllers\Web\TableOrderController;
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

Route::redirect('meja/{token}', '/pesan');

Route::get('pesan', [TableOrderController::class, 'show'])->name('order.menu');
Route::post('pesan/new-order', [TableOrderController::class, 'newOrder'])->name('order.menu.new');
Route::patch('pesan/customer', [TableOrderController::class, 'updateCustomer'])->name('order.menu.customer');
Route::post('pesan/items', [TableOrderController::class, 'addItem'])->name('order.menu.items');
Route::patch('pesan/items/{item}', [TableOrderController::class, 'updateItem'])->name('order.menu.items.update');
Route::delete('pesan/items/{item}', [TableOrderController::class, 'removeItem'])->name('order.menu.items.destroy');
Route::post('pesan/submit', [TableOrderController::class, 'submit'])->name('order.menu.submit');
Route::get('pesan/status', [TableOrderController::class, 'status'])->name('order.menu.status');

Route::get('manifest/{app}.webmanifest', [PwaController::class, 'manifest'])
    ->name('pwa.manifest')
    ->where('app', 'kasir|order');

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/', [KasirController::class, 'index'])->name('index');
    Route::get('/pending-orders/poll', [KasirController::class, 'pendingOrdersPoll'])->name('pending.poll');
    Route::get('/orders', [KasirController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [KasirController::class, 'showOrder'])->name('orders.show');
    Route::get('/tables', [KasirController::class, 'tables'])->name('tables');
    Route::get('/barcode', [KasirController::class, 'barcode'])->name('barcode');
    Route::post('/tables', [KasirController::class, 'storeTable'])->name('tables.store');
    Route::post('/new-order', [KasirController::class, 'newOrder'])->name('new-order');
    Route::patch('/order', [KasirController::class, 'updateOrder'])->name('order.update');
    Route::post('/cancel-order', [KasirController::class, 'cancelOrder'])->name('order.cancel');
    Route::post('/load-order/{order}', [KasirController::class, 'loadOrder'])->name('load-order');
    Route::post('/orders/{order}/confirm', [KasirController::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('/items', [KasirController::class, 'addItem'])->name('items.store');
    Route::patch('/items/{item}', [KasirController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{item}', [KasirController::class, 'removeItem'])->name('items.destroy');
    Route::get('/products', [KasirProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}/edit', [KasirProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [KasirProductController::class, 'update'])->name('products.update');
    Route::post('/pay', [KasirController::class, 'pay'])->name('pay');
    Route::get('/receipt/{order}', [KasirController::class, 'receipt'])->name('receipt');
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
