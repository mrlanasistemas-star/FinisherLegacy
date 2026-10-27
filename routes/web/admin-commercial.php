<?php

use App\Http\Controllers\Admin\Commercial\ActivationController as AdminActivationController;
use App\Http\Controllers\Admin\Commercial\AllyController as AdminAllyController;
use App\Http\Controllers\Admin\Commercial\OrderOperationsController as AdminOrderOperationsController;
use App\Http\Controllers\Admin\Commercial\PosController;
use App\Http\Controllers\Admin\Commercial\SupportCaseController as AdminSupportCaseController;
use App\Http\Controllers\AllyPortalController;
use Illuminate\Support\Facades\Route;

/*
| Owned by the admin-commercial work stream (docs/architecture/commerce-os.md
| §Administración comercial y operación): Venta rápida (POS), Aliados,
| Soporte, Activaciones, order shipping/delivery and the ally portal.
| Every route is gated by `can:` middleware; ally scoping is enforced in
| the controllers (App\Support\Commerce\PosLocations), never by the UI.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:dashboard.admin.view')->prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:pos.sell')->prefix('venta-rapida')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'show'])->name('show');
            Route::get('pieza', [PosController::class, 'lookup'])->middleware('throttle:120,1')->name('lookup');
            Route::post('/', [PosController::class, 'store'])->middleware('throttle:60,1')->name('store');
        });

        Route::middleware('can:allies.view')->group(function () {
            Route::get('aliados', [AdminAllyController::class, 'index'])->name('allies.index');
            Route::get('aliados/{ally:uuid}', [AdminAllyController::class, 'show'])->name('allies.show');
        });
        Route::middleware('can:allies.manage')->prefix('aliados')->name('allies.')->group(function () {
            Route::post('/', [AdminAllyController::class, 'store'])->name('store');
            Route::patch('{ally:uuid}', [AdminAllyController::class, 'update'])->name('update');
            Route::post('{ally:uuid}/usuarios', [AdminAllyController::class, 'attachUser'])->name('users.attach');
            Route::delete('{ally:uuid}/usuarios/{user}', [AdminAllyController::class, 'detachUser'])->name('users.detach');
            Route::post('{ally:uuid}/ubicaciones', [AdminAllyController::class, 'storeLocation'])->name('locations.store');
        });

        Route::middleware('can:support.view')->group(function () {
            Route::get('soporte', [AdminSupportCaseController::class, 'index'])->name('support.index');
            Route::get('soporte/{supportCase:uuid}', [AdminSupportCaseController::class, 'show'])->name('support.show');
        });
        Route::middleware('can:support.manage')->prefix('soporte')->name('support.')->group(function () {
            Route::post('/', [AdminSupportCaseController::class, 'store'])->name('store');
            Route::patch('{supportCase:uuid}', [AdminSupportCaseController::class, 'update'])->name('update');
            Route::post('{supportCase:uuid}/reemplazo', [AdminSupportCaseController::class, 'replace'])->name('replace');
            Route::post('{supportCase:uuid}/devolucion', [AdminSupportCaseController::class, 'returnTag'])->name('return');
            Route::post('{supportCase:uuid}/revocar', [AdminSupportCaseController::class, 'revoke'])->name('revoke');
        });

        Route::middleware('can:tags.view')->group(function () {
            Route::get('activaciones', [AdminActivationController::class, 'index'])->name('activations.index');
            Route::post('activaciones/{legacyTag:uuid}/reenviar', [AdminActivationController::class, 'resend'])
                ->middleware('throttle:20,1')
                ->name('activations.resend');
        });

        Route::middleware('can:orders.manage')->prefix('orders/{order:uuid}')->name('orders.')->group(function () {
            Route::patch('envio', [AdminOrderOperationsController::class, 'updateShipping'])->name('shipping.update');
            Route::post('entregado', [AdminOrderOperationsController::class, 'markDelivered'])->name('delivered');
        });
    });

    // Portal del aliado — an ally's staff sees ONLY their own ally(ies).
    Route::middleware('can:ally.portal')->prefix('aliado')->name('ally.')->group(function () {
        Route::get('/', [AllyPortalController::class, 'dashboard'])->name('dashboard');
        Route::post('incidencias', [AllyPortalController::class, 'reportIncident'])->middleware('throttle:10,1')->name('incidents.store');

        Route::prefix('venta-rapida')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'show'])->name('show');
            Route::get('pieza', [PosController::class, 'lookup'])->middleware('throttle:120,1')->name('lookup');
            Route::post('/', [PosController::class, 'store'])->middleware('throttle:60,1')->name('store');
        });
    });
});
