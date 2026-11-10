<?php

use App\Http\Controllers\Api\V1\Me\StoryEditorController;
use App\Http\Controllers\Api\V1\Me\StoryMediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1 story editor for the app (docs/api/v1.md §Editor del recuerdo)
|--------------------------------------------------------------------------
| The owner edits the memory a tag opens: race data, text, photos, videos,
| voice messages and photos bought on the site. Sanctum + `user.token`;
| owner only (404 otherwise). Limits per kind come from
| App\Services\Media\StoryMediaQuota — the app never hardcodes them.
*/
Route::prefix('v1/me')->name('api.v1.me.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::get('purchased-photos', [StoryMediaController::class, 'purchased'])->name('purchased-photos.index');

    Route::prefix('stories/{publicId}')->where(['publicId' => '[a-z0-9]{8,16}'])->name('stories.')->group(function () {
        Route::get('/', [StoryEditorController::class, 'show'])->name('show');
        Route::patch('/', [StoryEditorController::class, 'update'])->middleware('throttle:60,1')->name('update');

        Route::get('media', [StoryMediaController::class, 'index'])->name('media.index');
        Route::post('media', [StoryMediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
        Route::post('media/reorder', [StoryMediaController::class, 'reorder'])->name('media.reorder');
        Route::post('media/import', [StoryMediaController::class, 'import'])->middleware('throttle:30,1')->name('media.import');
        Route::delete('media/{uuid}', [StoryMediaController::class, 'destroy'])->whereUuid('uuid')->name('media.destroy');
    });
});
