<?php

use App\Http\Controllers\Admin\CommerceController as AdminCommerceController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\PaymentSettingsController as AdminPaymentSettingsController;
use App\Http\Controllers\Admin\ProductMediaController as AdminProductMediaController;
use App\Http\Controllers\Admin\Store\OrderController as AdminStoreOrderController;
use App\Http\Controllers\Store\CheckoutController as StoreCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Commerce (agent A2): checkout payment step + admin COMERCIO workspace
|--------------------------------------------------------------------------
| The original cart/checkout/admin product/order/coupon routes stay in
| routes/web.php with their URLs and names; this file only adds.
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('checkout/{order:uuid}')->name('store.checkout.')->group(function () {
        Route::get('/', [StoreCheckoutController::class, 'pay'])->name('pay');
        Route::get('confirmacion', [StoreCheckoutController::class, 'confirmation'])->name('confirmation');
        Route::get('estado', [StoreCheckoutController::class, 'status'])->middleware('throttle:60,1')->name('status');
    });

    Route::middleware('can:dashboard.admin.view')->prefix('admin')->name('admin.')->group(function () {
        // Resumen — visible to anyone with any commerce permission
        // (checked in the controller).
        Route::get('comercio', [AdminCommerceController::class, 'index'])->name('commerce.index');

        Route::middleware('can:orders.view')->get('comercio/ventas', [AdminCommerceController::class, 'sales'])->name('commerce.sales');

        Route::middleware('can:inventory.manage')->prefix('comercio/inventario')->name('commerce.inventory.')->group(function () {
            Route::get('/', [AdminInventoryController::class, 'index'])->name('index');
            Route::post('ajustar', [AdminInventoryController::class, 'adjust'])->name('adjust');
            Route::get('variantes/{variant}/movimientos', [AdminInventoryController::class, 'movements'])->name('movements');
            Route::patch('variantes/{variant}/minimo', [AdminInventoryController::class, 'updateMinimum'])->name('minimum');
        });

        Route::middleware('can:orders.manage')->post('orders/items/{item}/return', [AdminStoreOrderController::class, 'returnItem'])->name('orders.items.return');

        Route::middleware('can:products.manage')->post('products/media/{media}/poster', [AdminProductMediaController::class, 'poster'])->name('products.media.poster');

        // Pagos: active gateway + OpenPay keys (private key encrypted, write-only).
        Route::middleware('can:payments.configure')->prefix('configuracion/pagos')->name('settings.payments')->group(function () {
            Route::get('/', [AdminPaymentSettingsController::class, 'index']);
            Route::put('/', [AdminPaymentSettingsController::class, 'update'])->name('.update');
            Route::post('probar', [AdminPaymentSettingsController::class, 'test'])->middleware('throttle:10,1')->name('.test');
        });
    });
});
