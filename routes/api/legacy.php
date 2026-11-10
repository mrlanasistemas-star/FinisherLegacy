<?php

use App\Http\Controllers\Api\V1\LegacyTagActivationController;
use App\Http\Controllers\Api\V1\LegacyTagController;
use App\Support\LegacyTags\TagCode;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1 Finisher Legacy Tag + stories (docs/api/v1.md §Finisher Legacy Tag)
|--------------------------------------------------------------------------
| Additive. Public tag resolve (optional Bearer personalises owned_by_me),
| authenticated activation, the athlete's stories and a policy-checked
| story read.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    // NFC self-activation of a blank chip (docs/api/v1.md §Activación NFC). Before
    // tags/{code} so "activation" is never read as a tag code.
    Route::middleware(['auth:sanctum', 'user.token', 'throttle:api-claim'])
        ->prefix('tags/activation')
        ->name('tags.activation.')
        ->group(function () {
            Route::post('start', [LegacyTagActivationController::class, 'start'])->name('start');
            Route::post('{intent}/confirm', [LegacyTagActivationController::class, 'confirm'])->whereUuid('intent')->name('confirm');
            Route::post('{intent}/fail', [LegacyTagActivationController::class, 'fail'])->whereUuid('intent')->name('fail');
        });

    Route::get('tags/{code}', [LegacyTagController::class, 'show'])
        ->where('code', TagCode::PATTERN)
        ->middleware('throttle:tag-open')
        ->name('tags.show');

    Route::post('tags/{code}/activate', [LegacyTagController::class, 'activate'])
        ->where('code', TagCode::PATTERN)
        ->middleware(['auth:sanctum', 'user.token', 'throttle:api-claim'])
        ->name('tags.activate');

    Route::get('me/stories', [LegacyTagController::class, 'myStories'])
        ->middleware(['auth:sanctum', 'user.token'])
        ->name('me.stories.index');

    Route::get('stories/{publicId}', [LegacyTagController::class, 'story'])
        ->where('publicId', '[a-z0-9]{8,16}')
        ->middleware('throttle:tag-open')
        ->name('stories.show');
});
