<?php

use App\Http\Controllers\LegacyTags\ActivationController;
use App\Http\Controllers\LegacyTags\MyLegacyController;
use App\Http\Controllers\LegacyTags\StoryController;
use App\Http\Controllers\LegacyTags\StoryEditorController;
use App\Http\Controllers\LegacyTags\StoryPrintController;
use App\Http\Controllers\LegacyTags\StoryShareController;
use App\Http\Controllers\LegacyTags\TagOpenController;
use App\Support\LegacyTags\TagCode;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activación + Mi Legacy (docs/architecture/commerce-os.md §Activación y Mi Legacy)
|--------------------------------------------------------------------------
| /t/{code} is what the NFC chip carries (old `?src=qr` links still resolve;
| the tag no longer has a printed QR). Codes are random,
| never incremental, and every public lookup is behind `tag-open`.
*/

$legacyStoryId = '[a-z0-9]{8,16}';

Route::get('t/{code}', TagOpenController::class)
    ->where('code', TagCode::PATTERN)
    ->middleware('throttle:tag-open')
    ->name('tag.open');

Route::get('activar', [ActivationController::class, 'index'])->name('activate.index');
Route::post('activar', [ActivationController::class, 'lookup'])
    ->middleware('throttle:tag-open')
    ->name('activate.lookup');

Route::middleware('throttle:tag-open')->group(function () {
    Route::get('activar/{code}', [ActivationController::class, 'show'])
        ->where('code', TagCode::PATTERN)
        ->name('activate.show');
    Route::get('activar/{code}/continuar/{provider}', [ActivationController::class, 'continueTo'])
        ->where('code', TagCode::PATTERN)
        ->whereIn('provider', ['login', 'register'])
        ->name('activate.continue');
});

Route::middleware('auth')->group(function () {
    Route::post('activar/{code}', [ActivationController::class, 'store'])
        ->where('code', TagCode::PATTERN)
        ->middleware('throttle:api-claim')
        ->name('activate.store');
    Route::post('activar/{code}/recuerdo', [ActivationController::class, 'complete'])
        ->where('code', TagCode::PATTERN)
        ->name('activate.complete');
});

// Recipient link ("Para mamá") — no account, no app. 40-char token.
Route::get('legacy/para/{token}', [StoryController::class, 'share'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:tag-open')
    ->name('legacy.share.show');

Route::get('legacy/{story}', [StoryController::class, 'show'])
    ->where('story', $legacyStoryId)
    ->middleware('throttle:tag-open')
    ->name('legacy.story.show');

Route::middleware('auth')->group(function () use ($legacyStoryId) {
    Route::get('mi-legacy', MyLegacyController::class)->name('my-legacy.index');

    Route::prefix('mi-legacy/{story}')->where(['story' => $legacyStoryId])->name('my-legacy.')->group(function () {
        Route::get('editar', [StoryEditorController::class, 'edit'])->name('edit');
        Route::patch('/', [StoryEditorController::class, 'update'])->name('update');

        Route::post('media', [StoryEditorController::class, 'storeMedia'])->middleware('throttle:uploads')->name('media.store');
        Route::patch('media/orden', [StoryEditorController::class, 'reorderMedia'])->name('media.reorder');
        Route::patch('media/{media}', [StoryEditorController::class, 'updateMedia'])->whereNumber('media')->name('media.update');
        Route::delete('media/{media}', [StoryEditorController::class, 'destroyMedia'])->whereNumber('media')->name('media.destroy');

        Route::post('enlaces', [StoryShareController::class, 'store'])->middleware('throttle:30,1')->name('shares.store');
        Route::delete('enlaces/{share}', [StoryShareController::class, 'destroy'])->whereNumber('share')->name('shares.destroy');

        Route::get('imprimir', [StoryPrintController::class, 'show'])->name('print');
        Route::post('imprimir', [StoryPrintController::class, 'download'])->middleware('throttle:20,1')->name('print.download');
    });
});
