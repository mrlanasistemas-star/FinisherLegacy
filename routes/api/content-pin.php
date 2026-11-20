<?php

use App\Http\Controllers\Api\V1\Me\ContentPinController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1/me/pin — optional content PIN (docs/api/v1.md §NIP)
|--------------------------------------------------------------------------
| Security model: App\Services\Privacy\ContentPin. The DB-backed lockout
| (5 tries, then 1→60 min) is the real brute-force defence; the throttles
| below only cap request volume. Recovery mail is throttled hard.
*/
Route::prefix('v1/me/pin')->name('api.v1.me.pin.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::get('/', [ContentPinController::class, 'show'])->name('show');

    Route::middleware('throttle:20,1,content-pin')->group(function () {
        Route::post('/', [ContentPinController::class, 'store'])->name('store');
        Route::put('/', [ContentPinController::class, 'update'])->name('update');
        Route::post('unlock', [ContentPinController::class, 'unlock'])->name('unlock');
    });

    // These also accept the account password / emailed code: tighter cap.
    Route::middleware('throttle:6,1,content-pin-reauth')->group(function () {
        Route::delete('/', [ContentPinController::class, 'destroy'])->name('destroy');
        Route::post('reset', [ContentPinController::class, 'reset'])->name('reset');
    });

    Route::post('lock', [ContentPinController::class, 'lock'])->name('lock');
    Route::post('recovery-code', [ContentPinController::class, 'sendRecoveryCode'])->middleware('throttle:3,10,content-pin-mail')->name('recovery-code');
    Route::put('items', [ContentPinController::class, 'protect'])->middleware('throttle:60,1')->name('items.update');
});
