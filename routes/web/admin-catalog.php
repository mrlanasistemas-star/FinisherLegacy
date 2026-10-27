<?php

use App\Http\Controllers\Admin\Catalog\ChannelPriceController;
use App\Http\Controllers\Admin\Catalog\CollectionController;
use App\Http\Controllers\Admin\Catalog\InventoryLocationController;
use App\Http\Controllers\Admin\Catalog\InventoryLotController;
use App\Http\Controllers\Admin\Catalog\LegacyTagController;
use App\Http\Controllers\Admin\Catalog\SupplierController;
use App\Http\Controllers\Admin\Catalog\TagStockController;
use App\Http\Controllers\Admin\Catalog\TagTransferController;
use Illuminate\Support\Facades\Route;

// Owned by the admin-catalog work stream (docs/architecture/commerce-os.md
// §Administración: catálogo, inventario y tags). Every route is gated with
// `can:` here; the Vue pages only hide what the account cannot do.

Route::middleware(['auth', 'verified', 'can:dashboard.admin.view'])->prefix('admin')->name('admin.')->group(function () {
    // Catálogo → Colecciones
    Route::middleware('can:products.manage')->prefix('catalogo/colecciones')->name('catalog.collections.')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::get('crear', [CollectionController::class, 'create'])->name('create');
        Route::post('/', [CollectionController::class, 'store'])->name('store');
        Route::get('{collection:id}', [CollectionController::class, 'show'])->whereNumber('collection')->name('show');
        Route::get('{collection:id}/editar', [CollectionController::class, 'edit'])->whereNumber('collection')->name('edit');
        // POST (not PATCH): carries optional image uploads.
        Route::post('{collection:id}', [CollectionController::class, 'update'])->whereNumber('collection')->name('update');
        Route::delete('{collection:id}', [CollectionController::class, 'destroy'])->whereNumber('collection')->name('destroy');
    });

    // Productos → Precios por canal (lives on the product page)
    Route::middleware('can:products.manage')->name('catalog.channel-prices.')->group(function () {
        Route::post('products/{product}/precios-canal', [ChannelPriceController::class, 'store'])->name('store');
        Route::patch('products/precios-canal/{schedule}', [ChannelPriceController::class, 'toggle'])->name('toggle');
    });

    // Inventario
    Route::prefix('inventario')->group(function () {
        Route::middleware('can:inventory.manage')->prefix('ubicaciones')->name('catalog.locations.')->group(function () {
            Route::get('/', [InventoryLocationController::class, 'index'])->name('index');
            Route::post('/', [InventoryLocationController::class, 'store'])->name('store');
            Route::get('{location}', [InventoryLocationController::class, 'show'])->whereNumber('location')->name('show');
            Route::patch('{location}', [InventoryLocationController::class, 'update'])->whereNumber('location')->name('update');
            Route::delete('{location}', [InventoryLocationController::class, 'destroy'])->whereNumber('location')->name('destroy');
        });

        Route::middleware('can:inventory.manage')->get('existencias', [TagStockController::class, 'index'])->name('catalog.stock.index');

        Route::prefix('lotes')->name('catalog.lots.')->group(function () {
            Route::middleware('can:tags.view')->get('/', [InventoryLotController::class, 'index'])->name('index');
            Route::middleware('can:tags.manage')->group(function () {
                Route::get('crear', [InventoryLotController::class, 'create'])->name('create');
                Route::post('/', [InventoryLotController::class, 'store'])->name('store');
                Route::get('{lot:uuid}/codigos.csv', [InventoryLotController::class, 'export'])->name('export');
                Route::post('{lot:uuid}/recibir', [InventoryLotController::class, 'receive'])->name('receive');
            });
            Route::middleware('can:tags.view')->get('{lot:uuid}', [InventoryLotController::class, 'show'])->name('show');
        });

        Route::middleware('can:tags.manage')->prefix('transferir')->name('catalog.transfers.')->group(function () {
            Route::get('/', [TagTransferController::class, 'create'])->name('create');
            Route::post('/', [TagTransferController::class, 'store'])->name('store');
        });

        Route::prefix('proveedores')->name('catalog.suppliers.')->group(function () {
            Route::middleware('can:allies.view')->get('/', [SupplierController::class, 'index'])->name('index');
            Route::middleware('can:allies.manage')->group(function () {
                Route::post('/', [SupplierController::class, 'store'])->name('store');
                Route::patch('{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('update');
                Route::delete('{supplier}', [SupplierController::class, 'destroy'])->whereNumber('supplier')->name('destroy');
            });
        });
    });

    // Legacy → Tags (registro por unidad)
    Route::prefix('tags')->name('tags.')->group(function () {
        Route::middleware('can:tags.view')->group(function () {
            Route::get('/', [LegacyTagController::class, 'index'])->name('index');
            Route::get('{tag:uuid}', [LegacyTagController::class, 'show'])->name('show');
            Route::get('{tag:uuid}/qr.svg', [LegacyTagController::class, 'qr'])->name('qr');
        });

        Route::middleware('can:tags.manage')->prefix('{tag:uuid}')->group(function () {
            Route::post('desactivar', [LegacyTagController::class, 'disable'])->name('disable');
            Route::post('reactivar', [LegacyTagController::class, 'enable'])->name('enable');
            Route::post('reemplazar', [LegacyTagController::class, 'replace'])->name('replace');
            Route::post('devolver', [LegacyTagController::class, 'returnTag'])->name('return');
            Route::post('revocar', [LegacyTagController::class, 'revoke'])->name('revoke');
            Route::post('notas', [LegacyTagController::class, 'note'])->name('note');
        });
    });
});
