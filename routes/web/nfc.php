<?php

use App\Http\Controllers\Admin\LegacyNfc\ConnectedProductController;
use App\Http\Controllers\Admin\LegacyNfc\NfcTagController;
use App\Http\Controllers\Admin\LegacyNfc\WorkspaceController;
use App\Http\Controllers\Nfc\AppLinksController;
use App\Http\Controllers\Nfc\MyGearController;
use App\Http\Controllers\Nfc\NfcTapController;
use App\Support\Nfc\NfcToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NFC links of Legacy Plates (docs/NFC.md)
|--------------------------------------------------------------------------
*/

// What every chip opens: https://<domain>/n/{token}.
Route::get('n/{token}', [NfcTapController::class, 'show'])
    ->where('token', NfcToken::PATTERN)
    ->middleware('throttle:nfc-resolve')
    ->name('nfc.tap');
Route::get('n/{token}/continue/{provider}', [NfcTapController::class, 'continueTo'])
    ->where('token', NfcToken::PATTERN)
    ->middleware('throttle:nfc-resolve')
    ->name('nfc.continue');

// Universal links / App Links — 404 until the app identifiers are configured.
Route::get('.well-known/apple-app-site-association', [AppLinksController::class, 'apple'])->name('app-links.apple');
Route::get('.well-known/assetlinks.json', [AppLinksController::class, 'android'])->name('app-links.android');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('n/{token}/claim', [NfcTapController::class, 'claim'])
        ->where('token', NfcToken::PATTERN)
        ->middleware('throttle:api-claim')
        ->name('nfc.claim');

    // "Mi equipo" — URL and route name kept (navigation links it).
    Route::get('dashboard/my-gear', MyGearController::class)->name('dashboard.my-gear');

    // Admin → NFC chips of already-sold Legacy Plates (/n/{token}). The
    // "Preparación y entrega" production workspace that lived under
    // admin/legacy-nfc/preparacion was retired (docs/renovacion/retiro-modulos.md).
    Route::prefix('admin/legacy-nfc')->name('admin.legacy-nfc.')->group(function () {
        Route::get('/', WorkspaceController::class)->name('index');

        Route::middleware('can:nfc.view')->prefix('etiquetas')->name('tags.')->group(function () {
            Route::get('/', [NfcTagController::class, 'index'])->name('index');

            Route::middleware('can:nfc.manage')->group(function () {
                Route::post('generar', [NfcTagController::class, 'generate'])->middleware('throttle:20,1')->name('generate');
                Route::get('exportar', [NfcTagController::class, 'export'])->name('export');
                Route::post('{tag}/desactivar', [NfcTagController::class, 'disable'])->name('disable');
                Route::post('{tag}/activar', [NfcTagController::class, 'enable'])->name('enable');
                Route::post('{tag}/liberar', [NfcTagController::class, 'release'])->name('release');
            });
        });

        Route::middleware('can:nfc.view')->prefix('productos')->name('products.')->group(function () {
            Route::get('/', [ConnectedProductController::class, 'index'])->name('index');
            Route::middleware('can:nfc.manage')->post('/', [ConnectedProductController::class, 'store'])->name('store');
        });
    });
});
