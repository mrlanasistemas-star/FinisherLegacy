<?php

use App\Http\Controllers\Settings\ContentPinController;
use Illuminate\Support\Facades\Route;

/*
| Content PIN on the web (App\Services\Privacy\ContentPin): unlock / lock a
| session grant. Management (create, change, disable, recover) is in the app.
| The DB-backed lockout applies here exactly as on /api/v1/me/pin/unlock.
*/
Route::middleware('auth')->prefix('settings/content-pin')->name('content-pin.')->group(function () {
    Route::get('/', [ContentPinController::class, 'show'])->name('show');
    Route::post('unlock', [ContentPinController::class, 'unlock'])->middleware('throttle:20,1,content-pin-web')->name('unlock');
    Route::post('lock', [ContentPinController::class, 'lock'])->name('lock');
});
