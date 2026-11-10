<?php

use App\Http\Controllers\Admin\I18n\TranslationController;
use App\Http\Controllers\Api\V1\I18nController;
use App\Http\Controllers\I18n\CatalogController;
use App\Http\Controllers\I18n\LocaleController;
use Illuminate\Support\Facades\Route;

/*
| Idiomas y traducciones (docs/renovacion/idiomas.md). Loaded by
| App\Providers\I18nServiceProvider — not by routes/web.php or
| routes/api.php — so each group declares its own middleware.
|
| The `/en/...` public URLs are NOT routes: App\Http\Middleware\StripLocalePrefix
| removes the prefix before routing and the normal routes answer.
*/

// The web catalog: stateless and cacheable (no session, no cookies).
Route::get('lang/{locale}.json', CatalogController::class)
    ->where('locale', '[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})?')
    ->middleware('throttle:240,1')
    ->name('i18n.catalog');

Route::middleware('web')->group(function () {
    // The language switcher (guests and signed-in people).
    Route::post('idioma', LocaleController::class)->middleware('throttle:30,1')->name('locale.update');

    Route::middleware(['auth', 'verified', 'can:dashboard.admin.view', 'can:i18n.manage'])
        ->prefix('admin/idiomas')
        ->name('admin.i18n.')
        ->group(function () {
            Route::get('/', [TranslationController::class, 'index'])->name('index');
            Route::post('sincronizar', [TranslationController::class, 'sync'])->middleware('throttle:6,1')->name('sync');
            Route::patch('idiomas/{locale}', [TranslationController::class, 'toggleLocale'])->name('locales.update');
            Route::patch('textos/{translationString}', [TranslationController::class, 'update'])->whereNumber('translationString')->name('strings.update');
            Route::patch('contenido/{modelTranslation}', [TranslationController::class, 'updateContent'])->whereNumber('modelTranslation')->name('content.update');
        });
});

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    // Public: the app asks before anyone signs in.
    Route::get('i18n', [I18nController::class, 'index'])->name('i18n.index');
    Route::get('i18n/{locale}', [I18nController::class, 'show'])
        ->where('locale', '[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})?')
        ->name('i18n.show');

    Route::patch('me/locale', [I18nController::class, 'update'])
        ->middleware(['auth:sanctum', 'user.token'])
        ->name('me.locale.update');
});
