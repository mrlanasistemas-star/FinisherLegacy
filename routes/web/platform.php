<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform (A1) web routes
|--------------------------------------------------------------------------
| `/broadcasting/auth` (session) is registered by bootstrap/app.php
| (withRouting channels:). `/horizon` is registered by Horizon itself and
| gated in App\Providers\HorizonServiceProvider (super_admin only).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    // Bell dropdown + polling fallback (JSON). The inbox page and the
    // read/read-all actions stay in routes/web.php.
    Route::get('notifications/summary', [NotificationController::class, 'summary'])
        ->middleware('throttle:60,1')
        ->name('notifications.summary');
});
