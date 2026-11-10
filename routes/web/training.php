<?php

/*
|--------------------------------------------------------------------------
| Entrenamientos — "Envíame ánimo" (docs/renovacion/entrenamientos.md)
|--------------------------------------------------------------------------
| /animo/{token} is public (no account, no app): family and friends leave a
| voice/text cheer. /mi-entrenamiento is the athlete's side: links +
| playlist. Every athlete lookup is owner-scoped (404 for anyone else).
*/

use App\Http\Controllers\Training\CheerPublicController;
use App\Http\Controllers\Training\TrainingController;
use Illuminate\Support\Facades\Route;

Route::get('animo/{token}', [CheerPublicController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:cheer-open')
    ->name('cheer.show');

Route::post('animo/{token}', [CheerPublicController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:cheer-submit')
    ->name('cheer.store');

Route::middleware('auth')->prefix('mi-entrenamiento')->name('training.')->group(function () {
    Route::get('/', [TrainingController::class, 'index'])->name('index');

    Route::post('enlaces', [TrainingController::class, 'storeLink'])->middleware('throttle:30,1')->name('links.store');
    Route::delete('enlaces/{link}', [TrainingController::class, 'destroyLink'])->whereUuid('link')->name('links.destroy');

    Route::prefix('mensajes/{message}')->where(['message' => '[0-9a-fA-F-]{36}'])->name('messages.')->group(function () {
        Route::post('escuchado', [TrainingController::class, 'listened'])->name('listened');
        Route::post('favorito', [TrainingController::class, 'favorite'])->name('favorite');
        Route::post('bloquear', [TrainingController::class, 'block'])->name('block');
        Route::post('reportar', [TrainingController::class, 'report'])->middleware('throttle:20,1')->name('report');
        Route::delete('/', [TrainingController::class, 'hide'])->name('hide');
    });
});
