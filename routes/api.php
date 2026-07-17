<?php

use App\Http\Controllers\Api\Admin\AttendanceApiController as AdminAttendanceApiController;
use App\Http\Controllers\Api\Admin\DashboardApiController as AdminDashboardApiController;
use App\Http\Controllers\Api\Admin\EmployeeApiController;
use App\Http\Controllers\Api\Admin\SalaryApiController;
use App\Http\Controllers\Api\Admin\SettingsApiController;
use App\Http\Controllers\Api\Admin\UserAccessApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\Auth\ModuleHubController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\PinSetupController;
use App\Http\Controllers\Api\Auth\ProfileSetupController;
use App\Http\Controllers\Api\Auth\TokenAuthController;
use App\Http\Controllers\Api\Cogs\CogsCalcApiController;
use App\Http\Controllers\Api\Cogs\DashboardApiController as CogsDashboardApiController;
use App\Http\Controllers\Api\Cogs\MaterialApiController;
use App\Http\Controllers\Api\Cogs\MenuPricingApiController;
use App\Http\Controllers\Api\Cogs\OverheadApiController;
use App\Http\Controllers\Api\Cogs\ProductApiController;
use App\Http\Controllers\Api\Cogs\ProductionApiController;
use App\Http\Controllers\Api\Cogs\ResetDataApiController;
use App\Http\Controllers\Api\Kasir\KasTunaiController;
use App\Http\Controllers\Api\Kasir\MenuCategoryController as KasirMenuCategoryController;
use App\Http\Controllers\Api\Kasir\OrderHistoryController;
use App\Http\Controllers\Api\Kasir\PembukuanController;
use App\Http\Controllers\Api\Kasir\PinController;
use App\Http\Controllers\Api\Kasir\PosController;
use App\Http\Controllers\Api\Kasir\ProductController as KasirProductController;
use App\Http\Controllers\Api\Kasir\TableController;
use App\Http\Controllers\Api\Order\TableOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // ── Public auth ──────────────────────────────────────────────
    Route::post('auth/login', [TokenAuthController::class, 'login'])->name('auth.login');

    // ── Public absensi (scan QR toko) ─────────────────────────────
    Route::get('attendance/scan', [AttendanceApiController::class, 'scanShow'])->name('attendance.scan.show');
    Route::post('attendance/scan', [AttendanceApiController::class, 'scanStore'])->name('attendance.scan.store');

    // ── Public pesan online (QR meja) ─────────────────────────────
    Route::prefix('pesan')->name('pesan.')->group(function () {
        Route::get('/', [TableOrderController::class, 'show'])->name('show');
        Route::post('new-order', [TableOrderController::class, 'newOrder'])->name('new');
        Route::patch('customer', [TableOrderController::class, 'updateCustomer'])->name('customer');
        Route::post('items', [TableOrderController::class, 'addItem'])->name('items.store');
        Route::patch('items/{item}', [TableOrderController::class, 'updateItem'])->name('items.update');
        Route::delete('items/{item}', [TableOrderController::class, 'removeItem'])->name('items.destroy');
        Route::post('submit', [TableOrderController::class, 'submit'])->name('submit');
        Route::get('status', [TableOrderController::class, 'status'])->name('status');
    });

    Route::middleware('auth:sanctum')->group(function () {
        // ── Account / hub ────────────────────────────────────────
        Route::get('auth/me', [TokenAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [TokenAuthController::class, 'logout'])->name('auth.logout');
        Route::put('auth/password', [PasswordController::class, 'update'])->name('auth.password');
        Route::get('auth/hub', [ModuleHubController::class, 'index'])->name('auth.hub');
        Route::post('auth/hub/{module}', [ModuleHubController::class, 'switch'])->name('auth.hub.switch');
        Route::get('auth/pin-setup', [PinSetupController::class, 'show'])->name('auth.pin-setup.show');
        Route::put('auth/pin-setup', [PinSetupController::class, 'update'])->name('auth.pin-setup.update');
        Route::get('auth/profile-setup', [ProfileSetupController::class, 'show'])->name('auth.profile-setup.show');
        Route::put('auth/profile-setup', [ProfileSetupController::class, 'update'])->name('auth.profile-setup.update');
        Route::get('attendance/status', [AttendanceApiController::class, 'status'])->name('attendance.status');

        // ── Kasir ────────────────────────────────────────────────
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
                Route::get('orders/{order}/receipt/pdf', [PosController::class, 'receiptPdf'])->name('orders.receipt.pdf');

                Route::get('orders', [OrderHistoryController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [OrderHistoryController::class, 'show'])->name('orders.show');

                Route::get('tables', [TableController::class, 'index'])->name('tables.index');
                Route::post('tables', [TableController::class, 'store'])->name('tables.store');
                Route::get('barcode', [TableController::class, 'barcode'])->name('barcode');

                Route::get('products', [KasirProductController::class, 'index'])->name('products.index');
                Route::get('products/{product}', [KasirProductController::class, 'show'])->name('products.show');
                Route::put('products/{product}', [KasirProductController::class, 'update'])->name('products.update');
                Route::post('products/{product}', [KasirProductController::class, 'update'])->name('products.update.post');

                Route::get('menu-categories', [KasirMenuCategoryController::class, 'index'])->name('menu-categories.index');
                Route::post('menu-categories', [KasirMenuCategoryController::class, 'store'])->name('menu-categories.store');
                Route::delete('menu-categories/{menuCategory}', [KasirMenuCategoryController::class, 'destroy'])->name('menu-categories.destroy');

                Route::get('pembukuan', [PembukuanController::class, 'index'])->name('pembukuan.index');
                Route::get('pembukuan/pdf', [PembukuanController::class, 'pdf'])->name('pembukuan.pdf');

                Route::get('kas-tunai', [KasTunaiController::class, 'index'])->name('kas-tunai.index');
                Route::post('kas-tunai/float', [KasTunaiController::class, 'storeFloat'])->name('kas-tunai.float');
                Route::post('kas-tunai/expense', [KasTunaiController::class, 'storeExpense'])->name('kas-tunai.expense');
            });
        });

        // ── COGS ─────────────────────────────────────────────────
        Route::middleware(['role:cogs', 'api.attendance'])->prefix('cogs')->name('cogs.')->group(function () {
            Route::get('dashboard', [CogsDashboardApiController::class, 'index'])->name('dashboard');

            Route::get('reset-data', [ResetDataApiController::class, 'show'])->name('reset-data.show');
            Route::post('reset-data', [ResetDataApiController::class, 'reset'])->name('reset-data.reset');

            Route::get('products', [ProductApiController::class, 'index'])->name('products.index');
            Route::post('products', [ProductApiController::class, 'store'])->name('products.store');
            Route::get('products/{product}', [ProductApiController::class, 'show'])->name('products.show');
            Route::put('products/{product}', [ProductApiController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductApiController::class, 'destroy'])->name('products.destroy');
            Route::post('products/{product}/bom', [ProductApiController::class, 'storeBom'])->name('products.bom.store');
            Route::put('products/{product}/bom/{bom}', [ProductApiController::class, 'updateBom'])->name('products.bom.update');
            Route::delete('products/{product}/bom/{bom}', [ProductApiController::class, 'destroyBom'])->name('products.bom.destroy');
            Route::post('products/{product}/addons', [ProductApiController::class, 'storeAddon'])->name('products.addons.store');
            Route::put('products/{product}/addons/{addon}', [ProductApiController::class, 'updateAddon'])->name('products.addons.update');
            Route::delete('products/{product}/addons/{addon}', [ProductApiController::class, 'destroyAddon'])->name('products.addons.destroy');
            Route::post('products/{product}/hitung-modal', [ProductApiController::class, 'calculateModal'])->name('products.calculate-modal');

            Route::get('materials', [MaterialApiController::class, 'index'])->name('materials.index');
            Route::get('materials/pdf', [MaterialApiController::class, 'pdf'])->name('materials.pdf');
            Route::get('materials/history', [MaterialApiController::class, 'history'])->name('materials.history');
            Route::post('materials', [MaterialApiController::class, 'storeMaterial'])->name('materials.store');
            Route::put('materials/{product}', [MaterialApiController::class, 'updateMaterial'])->name('materials.update');
            Route::delete('materials/{product}', [MaterialApiController::class, 'destroyMaterial'])->name('materials.destroy');
            Route::post('materials/stock', [MaterialApiController::class, 'receive'])->name('materials.receive');
            Route::put('materials/{product}/stock-remaining', [MaterialApiController::class, 'adjust'])->name('materials.adjust');
            Route::put('materials/lots/{lot}', [MaterialApiController::class, 'updateLot'])->name('materials.lots.update');
            Route::delete('materials/lots/{lot}', [MaterialApiController::class, 'destroyLot'])->name('materials.lots.destroy');

            Route::get('production-orders', [ProductionApiController::class, 'index'])->name('production-orders.index');
            Route::post('production-orders', [ProductionApiController::class, 'store'])->name('production-orders.store');
            Route::get('production-orders/{production_order}', [ProductionApiController::class, 'show'])->name('production-orders.show');
            Route::put('production-orders/{production_order}', [ProductionApiController::class, 'update'])->name('production-orders.update');
            Route::delete('production-orders/{production_order}', [ProductionApiController::class, 'destroy'])->name('production-orders.destroy');
            Route::post('production-orders/{production_order}/start', [ProductionApiController::class, 'start'])->name('production-orders.start');
            Route::post('production-orders/{production_order}/complete', [ProductionApiController::class, 'complete'])->name('production-orders.complete');

            Route::get('menu-pricing', [MenuPricingApiController::class, 'index'])->name('menu-pricing.index');
            Route::put('menu-pricing/{product}', [MenuPricingApiController::class, 'update'])->name('menu-pricing.update');

            Route::post('calculate', [CogsCalcApiController::class, 'process'])->name('calculate');
            Route::get('result', [CogsCalcApiController::class, 'result'])->name('result');
            Route::get('history', [CogsCalcApiController::class, 'history'])->name('history');
            Route::get('history/{calculation}', [CogsCalcApiController::class, 'show'])->name('history.show');
            Route::delete('history/{calculation}', [CogsCalcApiController::class, 'destroy'])->name('history.destroy');
            Route::get('products/{product}/roll-up', [CogsCalcApiController::class, 'rollUp'])->name('roll-up');
            Route::get('summary', [CogsCalcApiController::class, 'summary'])->name('summary');

            Route::get('overhead-rates', [OverheadApiController::class, 'index'])->name('overhead-rates.index');
            Route::post('overhead-rates', [OverheadApiController::class, 'store'])->name('overhead-rates.store');
            Route::put('overhead-rates/{overheadRate}', [OverheadApiController::class, 'update'])->name('overhead-rates.update');
            Route::delete('overhead-rates/{overheadRate}', [OverheadApiController::class, 'destroy'])->name('overhead-rates.destroy');
        });

        // ── Admin ────────────────────────────────────────────────
        Route::middleware(['role:admin', 'api.attendance'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminDashboardApiController::class, 'index'])->name('dashboard');

            Route::get('employees', [EmployeeApiController::class, 'index'])->name('employees.index');
            Route::post('employees', [EmployeeApiController::class, 'store'])->name('employees.store');
            Route::get('employees/{employee}', [EmployeeApiController::class, 'show'])->name('employees.show');
            Route::put('employees/{employee}', [EmployeeApiController::class, 'update'])->name('employees.update');
            Route::delete('employees/{employee}', [EmployeeApiController::class, 'destroy'])->name('employees.destroy');
            Route::post('employees/{employee}/face', [EmployeeApiController::class, 'enrollFace'])->name('employees.face');

            Route::get('attendances', [AdminAttendanceApiController::class, 'index'])->name('attendances.index');
            Route::get('attendances/qr', [AdminAttendanceApiController::class, 'qrInfo'])->name('attendances.qr');
            Route::post('attendances', [AdminAttendanceApiController::class, 'store'])->name('attendances.store');
            Route::delete('attendances/{attendance}', [AdminAttendanceApiController::class, 'destroy'])->name('attendances.destroy');

            Route::get('salaries', [SalaryApiController::class, 'index'])->name('salaries.index');
            Route::post('salaries', [SalaryApiController::class, 'store'])->name('salaries.store');
            Route::post('salaries/{salary}/paid', [SalaryApiController::class, 'markPaid'])->name('salaries.paid');
            Route::delete('salaries/{salary}', [SalaryApiController::class, 'destroy'])->name('salaries.destroy');

            Route::get('users', [UserAccessApiController::class, 'index'])->name('users.index');
            Route::post('users', [UserAccessApiController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserAccessApiController::class, 'show'])->name('users.show');
            Route::put('users/{user}', [UserAccessApiController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserAccessApiController::class, 'destroy'])->name('users.destroy');

            Route::get('settings', [SettingsApiController::class, 'show'])->name('settings.show');
            Route::put('settings', [SettingsApiController::class, 'update'])->name('settings.update');
        });
    });
});
