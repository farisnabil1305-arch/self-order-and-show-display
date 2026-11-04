<?php

use App\Http\Controllers\DisplayController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

// Home redirect to Kiosk
Route::get('/', function () {
    return redirect()->route('kiosk.index');
});

// Customer Self-Order Kiosk
Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('index');
    Route::get('/api/tables', [KioskController::class, 'getTables'])->name('tables.api');
    Route::post('/order', [KioskController::class, 'storeOrder'])->name('order.store');
});

// Cashier Tablet POS
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::get('/api/orders', [PosController::class, 'getOrders'])->name('orders.api');
    Route::get('/api/revenue-recap', [PosController::class, 'getRevenueRecap'])->name('revenue.recap');
    Route::post('/orders/{order}/pay', [PosController::class, 'processPayment'])->name('orders.pay');
    Route::post('/orders/{order}/cancel', [PosController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/orders/{order}/call', [PosController::class, 'callOrder'])->name('orders.call');
    Route::delete('/orders/{order}', [PosController::class, 'destroyOrder'])->name('orders.destroy');
    Route::post('/orders/clear-all', [PosController::class, 'clearAllOrders'])->name('orders.clearAll');
    Route::post('/products/{product}/toggle', [PosController::class, 'toggleProductAvailability'])->name('products.toggle');

    // Table Management
    Route::get('/api/tables', [PosController::class, 'getTables'])->name('tables.api');
    Route::post('/tables/{table}/release', [PosController::class, 'releaseTable'])->name('tables.release');
    Route::post('/tables/{table}/toggle', [PosController::class, 'toggleTableStatus'])->name('tables.toggle');

    // Menu & Category Management (Input Manual lewat Web)
    Route::get('/api/menu-data', [PosController::class, 'getMenuData'])->name('menu.data');
    Route::post('/categories', [PosController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{category}', [PosController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/products', [PosController::class, 'storeProduct'])->name('products.store');
    Route::post('/products/{product}/update', [PosController::class, 'updateProduct'])->name('products.update');
    Route::delete('/products/{product}', [PosController::class, 'destroyProduct'])->name('products.destroy');

    // Store Settings & Profile
    Route::get('/api/store-settings', [PosController::class, 'getStoreSettings'])->name('settings.get');
    Route::post('/api/store-settings', [PosController::class, 'updateStoreSettings'])->name('settings.update');

    // End of Day / Shift Z-Report
    Route::get('/api/z-report', [PosController::class, 'getZReportData'])->name('zreport.data');

    // Export Sales Report to CSV
    Route::get('/reports/export-csv', [PosController::class, 'exportCsv'])->name('reports.exportCsv');
});

// Kitchen Display System (Tablet Dapur KDS)
Route::prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', [KitchenController::class, 'index'])->name('index');
    Route::get('/api/orders', [KitchenController::class, 'getOrders'])->name('orders.api');
    Route::post('/orders/{order}/start', [KitchenController::class, 'startCooking'])->name('orders.start');
    Route::post('/orders/{order}/ready', [KitchenController::class, 'markReady'])->name('orders.ready');
    Route::post('/orders/{order}/complete', [KitchenController::class, 'markCompleted'])->name('orders.complete');
    Route::post('/orders/{order}/recall', [KitchenController::class, 'recallOrder'])->name('orders.recall');
});

// Customer TV Queue Display (Layar TV Antrean Pelanggan)
Route::prefix('display')->name('display.')->group(function () {
    Route::get('/', [DisplayController::class, 'index'])->name('index');
    Route::get('/api/queue', [DisplayController::class, 'getQueue'])->name('queue.api');
});
