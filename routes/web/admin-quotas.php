<?php

use App\Http\Controllers\Admin\Quotas\ExpansionPackController;
use App\Http\Controllers\Admin\Quotas\ExpansionPriceController;
use App\Http\Controllers\Admin\Quotas\QuotaController;
use App\Http\Controllers\Admin\Quotas\QuotaRuleController;
use App\Http\Controllers\Quotas\PhotoExpansionCheckoutController;
use Illuminate\Support\Facades\Route;

/*
| Límites y tarifas (docs/renovacion/cuotas-y-precios.md): base photo
| limits per product / product type, photo expansion packs and their
| price schedules. Gated by `quotas.manage`. Prices are never edited
| once they started: a new price is a new schedule row.
|
| Plus the owner-side purchase of an expansion, which only creates a
| pending Order — the capacity is granted after the payment is confirmed
| server-side (MarkOrderPaid → GrantPhotoExpansionEntitlements).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(['can:dashboard.admin.view', 'can:quotas.manage'])->prefix('admin/limites-y-tarifas')->name('admin.quotas.')->group(function () {
        Route::get('/', [QuotaController::class, 'index'])->name('index');

        Route::post('limites', [QuotaRuleController::class, 'store'])->name('rules.store');
        Route::delete('limites/{rule}', [QuotaRuleController::class, 'destroy'])->whereNumber('rule')->name('rules.destroy');

        Route::post('ampliaciones', [ExpansionPackController::class, 'store'])->name('packs.store');
        Route::patch('ampliaciones/{pack}', [ExpansionPackController::class, 'update'])->whereNumber('pack')->name('packs.update');
        Route::post('ampliaciones/{pack}/archivar', [ExpansionPackController::class, 'archive'])->whereNumber('pack')->name('packs.archive');
        Route::post('ampliaciones/{pack}/restaurar', [ExpansionPackController::class, 'restore'])->whereNumber('pack')->name('packs.restore');
        Route::delete('ampliaciones/{pack}', [ExpansionPackController::class, 'destroy'])->whereNumber('pack')->name('packs.destroy');

        Route::post('ampliaciones/{pack}/precios', [ExpansionPriceController::class, 'store'])->whereNumber('pack')->name('prices.store');
        Route::post('precios/{schedule}/terminar', [ExpansionPriceController::class, 'end'])->whereNumber('schedule')->name('prices.end');
        Route::post('precios/{schedule}/cancelar', [ExpansionPriceController::class, 'cancel'])->whereNumber('schedule')->name('prices.cancel');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('mi-legacy/{story}/ampliar-fotos', PhotoExpansionCheckoutController::class)
        ->where('story', '[a-z0-9]{8,16}')
        ->middleware('throttle:30,1')
        ->name('my-legacy.photo-expansion.store');
});
