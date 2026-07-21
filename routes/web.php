<?php

use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AttendanceController;
use App\Http\Controllers\Web\Admin\EmployeeController;
use App\Http\Controllers\Web\Admin\SalaryController;
use App\Http\Controllers\Web\Admin\SettingsController;
use App\Http\Controllers\Web\Admin\UserAccessController;
use App\Http\Controllers\Web\Admin\AttendanceQrController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\EmployeeProfileSetupController;
use App\Http\Controllers\Web\PublicAttendanceController;
use App\Http\Controllers\Web\Auth\ModuleHubController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\Auth\PinSetupController;
use App\Http\Controllers\Web\CogsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BahanJadiController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\StockWasteController;
use App\Http\Controllers\Web\OpsAssetController;
use App\Http\Controllers\Web\Kasir\KasirPinController;
use App\Http\Controllers\Web\Kasir\WasteController as KasirWebWasteController;
use App\Http\Controllers\Web\KasirController;
use App\Http\Controllers\Web\KasirPushController;
use App\Http\Controllers\Web\KasirProductController;
use App\Http\Controllers\Web\MenuCategoryController;
use App\Http\Controllers\Web\OverheadRateController;
use App\Http\Controllers\Web\KasTunaiController;
use App\Http\Controllers\Web\MenuPricingController;
use App\Http\Controllers\Web\PembukuanController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductionOrderController;
use App\Http\Controllers\Web\PwaController;
use App\Http\Controllers\Web\ResetDataController;
use App\Http\Controllers\Web\TableOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'create'])->name('home');
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/hub', [ModuleHubController::class, 'index'])->name('hub');
    Route::get('/hub/{module}', [ModuleHubController::class, 'switch'])->name('hub.switch');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
    Route::get('/pin', [PinSetupController::class, 'edit'])->name('pin.edit');
    Route::put('/pin', [PinSetupController::class, 'update'])->name('pin.update');

    Route::get('/attendance/check-in', fn () => redirect()->route('attendance.scan'))->name('attendance.check-in');
    Route::post('/attendance/check-in', fn () => redirect()->route('attendance.scan'))->name('attendance.check-in.store');
    Route::get('/attendance/check-out', fn () => redirect()->route('attendance.scan'))->name('attendance.check-out');
    Route::post('/attendance/check-out', fn () => redirect()->route('attendance.scan'))->name('attendance.check-out.store');

    Route::get('/employee/profile-setup', [EmployeeProfileSetupController::class, 'edit'])->name('employee.profile.setup');
    Route::put('/employee/profile-setup', [EmployeeProfileSetupController::class, 'update'])->name('employee.profile.setup.update');
});

Route::redirect('meja/{token}', '/pesan');

Route::get('absensi', [PublicAttendanceController::class, 'show'])->name('attendance.scan');
Route::post('absensi', [PublicAttendanceController::class, 'store'])->name('attendance.scan.store');

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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('employees', EmployeeController::class)->except(['show']);
    Route::post('employees/{employee}/face', [EmployeeController::class, 'enrollFace'])->name('employees.face');
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/qr', [AttendanceQrController::class, 'show'])->name('attendances.qr');
    Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
    Route::get('salaries', [SalaryController::class, 'index'])->name('salaries.index');
    Route::post('salaries', [SalaryController::class, 'store'])->name('salaries.store');
    Route::post('salaries/{salary}/paid', [SalaryController::class, 'markPaid'])->name('salaries.paid');
    Route::delete('salaries/{salary}', [SalaryController::class, 'destroy'])->name('salaries.destroy');
    Route::resource('users', UserAccessController::class);
    Route::post('users/{user}/reset-password', [UserAccessController::class, 'resetPassword'])->name('users.reset-password');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/pin', [KasirPinController::class, 'show'])->name('pin.unlock');
    Route::post('/pin', [KasirPinController::class, 'unlock'])->name('pin.unlock.submit');
    Route::post('/pin/lock', [KasirPinController::class, 'lock'])->name('pin.lock');
    Route::get('/pin/status', [KasirPinController::class, 'status'])->name('pin.status');
    Route::post('/pin/touch', [KasirPinController::class, 'touch'])->name('pin.touch');
    Route::get('/pending-orders/poll', [KasirController::class, 'pendingOrdersPoll'])->name('pending.poll');
    Route::get('/push/vapid-key', [KasirPushController::class, 'vapidPublicKey'])->name('push.vapid');
    Route::post('/push/subscribe', [KasirPushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [KasirPushController::class, 'unsubscribe'])->name('push.unsubscribe');

    Route::middleware('kasir.pin')->group(function () {
        Route::get('/', [KasirController::class, 'index'])->name('index');
        Route::get('/orders', [KasirController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [KasirController::class, 'showOrder'])->name('orders.show');
        Route::get('/tables', [KasirController::class, 'tables'])->name('tables');
        Route::get('/barcode', [KasirController::class, 'barcode'])->name('barcode');
        Route::post('/tables', [KasirController::class, 'storeTable'])->name('tables.store');
        Route::post('/new-order', [KasirController::class, 'newOrder'])->name('new-order');
        Route::patch('/order', [KasirController::class, 'updateOrder'])->name('order.update');
        Route::patch('/discount', [KasirController::class, 'updateDiscount'])->name('discount.update');
        Route::post('/cancel-order', [KasirController::class, 'cancelOrder'])->name('order.cancel');
        Route::post('/load-order/{order}', [KasirController::class, 'loadOrder'])->name('load-order');
        Route::post('/orders/{order}/confirm', [KasirController::class, 'confirmOrder'])->name('orders.confirm');
        Route::post('/orders/{order}/serve', [KasirController::class, 'markServed'])->name('orders.serve');
        Route::post('/orders/{order}/cancel', [KasirController::class, 'cancelPendingOrder'])->name('orders.cancel');
        Route::post('/items', [KasirController::class, 'addItem'])->name('items.store');
        Route::patch('/items/{item}', [KasirController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{item}', [KasirController::class, 'removeItem'])->name('items.destroy');
        Route::get('/products', [KasirProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}/edit', [KasirProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [KasirProductController::class, 'update'])->name('products.update');
        Route::get('/menu-categories', [MenuCategoryController::class, 'index'])->name('menu-categories.index');
        Route::post('/menu-categories', [MenuCategoryController::class, 'store'])->name('menu-categories.store');
        Route::delete('/menu-categories/{menuCategory}', [MenuCategoryController::class, 'destroy'])->name('menu-categories.destroy');
        Route::get('/pembukuan', [PembukuanController::class, 'index'])->name('pembukuan.index');
        Route::get('/pembukuan/pdf', [PembukuanController::class, 'pdf'])->name('pembukuan.pdf');
        Route::get('/kas-tunai', [KasTunaiController::class, 'index'])->name('kas-tunai.index');
        Route::post('/kas-tunai/float', [KasTunaiController::class, 'storeFloat'])->name('kas-tunai.float');
        Route::post('/kas-tunai/expense', [KasTunaiController::class, 'storeExpense'])->name('kas-tunai.expense');
        Route::post('/pay', [KasirController::class, 'pay'])->name('pay');
        Route::post('/open-bill', [KasirController::class, 'openBill'])->name('open-bill');
        Route::get('/receipt/{order}', [KasirController::class, 'receipt'])->name('receipt');
        Route::get('/receipt/{order}/pdf', [KasirController::class, 'receiptPdf'])->name('receipt.pdf');
        Route::post('/waste', [KasirWebWasteController::class, 'store'])->name('waste.store');
    });
});

Route::middleware(['auth', 'role:cogs', 'cogs.route'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('reset-data', [ResetDataController::class, 'show'])->name('reset-data.show');
    Route::post('reset-data', [ResetDataController::class, 'reset'])->name('reset-data.store');

    Route::resource('products', ProductController::class);
    Route::post('products/{product}/bom', [ProductController::class, 'storeBom'])->name('products.bom.store');
    Route::put('products/{product}/bom/{bom}', [ProductController::class, 'updateBom'])->name('products.bom.update');
    Route::delete('products/{product}/bom/{bom}', [ProductController::class, 'destroyBom'])->name('products.bom.destroy');
    Route::post('products/{product}/addons', [ProductController::class, 'storeAddon'])->name('products.addons.store');
    Route::put('products/{product}/addons/{addon}', [ProductController::class, 'updateAddon'])->name('products.addons.update');
    Route::delete('products/{product}/addons/{addon}', [ProductController::class, 'destroyAddon'])->name('products.addons.destroy');
    Route::post('products/{product}/hitung-modal', [ProductController::class, 'calculateModal'])->name('products.calculate-modal');

    Route::get('bahan', [InventoryController::class, 'index'])->name('materials.index');
    Route::get('bahan/pdf', [InventoryController::class, 'pdf'])->name('materials.pdf');
    Route::get('bahan/riwayat', [InventoryController::class, 'history'])->name('materials.history');
    Route::post('bahan', [InventoryController::class, 'storeMaterial'])->name('materials.store');
    Route::put('bahan/{product}', [InventoryController::class, 'updateMaterial'])->name('materials.update');
    Route::delete('bahan/{product}', [InventoryController::class, 'destroyMaterial'])->name('materials.destroy');
    Route::post('bahan/stok', [InventoryController::class, 'receive'])->name('materials.receive');
    Route::put('bahan/{product}/stok-sisa', [InventoryController::class, 'adjust'])->name('materials.stock.adjust');
    Route::put('bahan/stok/{lot}', [InventoryController::class, 'update'])->name('materials.lots.update');
    Route::delete('bahan/stok/{lot}', [InventoryController::class, 'destroy'])->name('materials.lots.destroy');
    Route::redirect('inventory', '/bahan');

    Route::get('bahan-jadi', [BahanJadiController::class, 'index'])->name('bahan-jadi.index');
    Route::post('bahan-jadi', [BahanJadiController::class, 'store'])->name('bahan-jadi.store');
    Route::put('bahan-jadi/{product}', [BahanJadiController::class, 'update'])->name('bahan-jadi.update');
    Route::delete('bahan-jadi/{product}', [BahanJadiController::class, 'destroy'])->name('bahan-jadi.destroy');
    Route::post('bahan-jadi/stok', [BahanJadiController::class, 'receive'])->name('bahan-jadi.receive');
    Route::post('bahan-jadi/{product}/bom', [BahanJadiController::class, 'storeBom'])->name('bahan-jadi.bom.store');
    Route::put('bahan-jadi/{product}/bom/{bom}', [BahanJadiController::class, 'updateBom'])->name('bahan-jadi.bom.update');
    Route::delete('bahan-jadi/{product}/bom/{bom}', [BahanJadiController::class, 'destroyBom'])->name('bahan-jadi.bom.destroy');

    Route::resource('production-orders', ProductionOrderController::class);
    Route::post('production-orders/{production_order}/start', [ProductionOrderController::class, 'start'])->name('production-orders.start');
    Route::post('production-orders/{production_order}/complete', [ProductionOrderController::class, 'complete'])->name('production-orders.complete');

    Route::get('harga-jual', [MenuPricingController::class, 'index'])->name('menu-pricing.index');
    Route::put('harga-jual/{product}', [MenuPricingController::class, 'update'])->name('menu-pricing.update');

    Route::get('cogs/calculate', fn () => redirect()->route('menu-pricing.index'))->name('cogs.calculate');
    Route::post('cogs/calculate', [CogsController::class, 'process'])->name('cogs.process');
    Route::get('cogs/result', [CogsController::class, 'result'])->name('cogs.result');
    Route::get('cogs/history', [CogsController::class, 'history'])->name('cogs.history');
    Route::get('cogs/history/{calculation}', [CogsController::class, 'show'])->name('cogs.history.show');
    Route::delete('cogs/history/{calculation}', [CogsController::class, 'destroy'])->name('cogs.history.destroy');

    Route::get('stok-rusak', [StockWasteController::class, 'index'])->name('stock-wastes.index');
    Route::post('stok-rusak', [StockWasteController::class, 'store'])->name('stock-wastes.store');

    Route::get('inventaris-operasional', [OpsAssetController::class, 'index'])->name('ops-assets.index');
    Route::post('inventaris-operasional', [OpsAssetController::class, 'store'])->name('ops-assets.store');
    Route::post('inventaris-operasional/{opsAsset}/terima', [OpsAssetController::class, 'receive'])->name('ops-assets.receive');
    Route::post('inventaris-operasional/{opsAsset}/rusak', [OpsAssetController::class, 'damage'])->name('ops-assets.damage');

    Route::resource('overhead-rates', OverheadRateController::class)->except(['show']);
});
