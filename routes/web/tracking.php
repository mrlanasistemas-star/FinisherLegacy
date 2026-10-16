<?php

use App\Http\Controllers\Admin\EventTrackingController;
use App\Http\Controllers\AthleteTrackingController;
use App\Http\Controllers\LiveTrackingPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Live tracking ("Seguimiento en vivo", SPEC §75-§84)
|--------------------------------------------------------------------------
*/

// Family live page — public, no account, reached only via a share token.
Route::get('live/{token}', [LiveTrackingPageController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,128}')
    ->middleware('throttle:live-view')
    ->name('live.show');

Route::middleware(['auth', 'verified'])->group(function () {
    // Athlete side, inside Mi legado's participation detail. Start/stop
    // happen only in the app.
    Route::post('dashboard/legado/{participant}/tracking/shares', [AthleteTrackingController::class, 'storeShare'])
        ->middleware('throttle:20,1')
        ->name('dashboard.tracking.shares.store');
    Route::delete('dashboard/tracking/shares/{share:uuid}', [AthleteTrackingController::class, 'destroyShare'])
        ->name('dashboard.tracking.shares.destroy');
    Route::post('dashboard/legado/{participant}/tracking/devices', [AthleteTrackingController::class, 'storeDevice'])
        ->middleware('throttle:10,1')
        ->name('dashboard.tracking.devices.store');
    Route::delete('dashboard/tracking/devices/{device:uuid}', [AthleteTrackingController::class, 'destroyDevice'])
        ->name('dashboard.tracking.devices.destroy');

    // Admin event workspace → Tracking tab (A4 adds the tab link).
    Route::middleware(['can:dashboard.admin.view', 'can:events.view'])
        ->get('admin/events/{eventEdition}/tracking', [EventTrackingController::class, 'show'])
        ->name('admin.events.tracking');
});
