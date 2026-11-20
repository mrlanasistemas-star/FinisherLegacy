<?php

use App\Http\Controllers\Api\V1\Family\FamilyFollowingController;
use App\Http\Controllers\Api\V1\Family\FamilyLinkController;
use App\Http\Controllers\Api\V1\Tracking\LiveTrackingController;
use App\Http\Controllers\Api\V1\Tracking\TrackingDeviceController;
use App\Http\Controllers\Api\V1\Tracking\TrackingSessionController;
use App\Http\Controllers\Api\V1\Tracking\TrackingSharingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Live tracking API (SPEC §75-§84) — contract in AGENT_BRIEF + TrackingSessionController.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public family snapshot.
    Route::get('live/{token}', [LiveTrackingController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{20,128}')
        ->middleware('throttle:live-view')
        ->name('live.show');

    // Ingest: session owner (Sanctum) OR paired device token — outside
    // auth:sanctum on purpose; IngestTrackingPointsRequest authorizes.
    Route::post('tracking/sessions/{session:uuid}/points', [TrackingSessionController::class, 'points'])
        ->middleware('throttle:tracking-ingest')
        ->name('tracking.sessions.points');

    // gps_band device contract.
    Route::post('tracking/devices/pair', [TrackingDeviceController::class, 'pair'])
        ->middleware('throttle:10,1')
        ->name('tracking.devices.pair');
    Route::post('tracking/devices/session', [TrackingDeviceController::class, 'startSession'])
        ->middleware('throttle:tracking-ingest')
        ->name('tracking.devices.session');

    Route::middleware(['auth:sanctum', 'user.token'])->group(function () {
        Route::get('me/events/{participant}/tracking', [TrackingSessionController::class, 'summary'])
            ->name('me.events.tracking');
        Route::post('me/events/{participant}/tracking/sessions', [TrackingSessionController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('me.events.tracking.sessions.store');

        Route::get('me/tracking/sessions/active', [TrackingSessionController::class, 'active'])
            ->name('me.tracking.sessions.active');

        Route::get('tracking/sessions/{session:uuid}', [TrackingSessionController::class, 'show'])->name('tracking.sessions.show');
        Route::get('tracking/sessions/{session:uuid}/shares', [TrackingSessionController::class, 'shares'])->name('tracking.sessions.shares.index');
        Route::post('tracking/sessions/{session:uuid}/stop', [TrackingSessionController::class, 'stop'])->name('tracking.sessions.stop');
        Route::post('tracking/sessions/{session:uuid}/shares', [TrackingSessionController::class, 'share'])
            ->middleware('throttle:20,1')
            ->name('tracking.sessions.shares.store');
        Route::delete('tracking/shares/{share:uuid}', [TrackingSessionController::class, 'revokeShare'])->name('tracking.shares.destroy');

        // Who sees my live location (docs/renovacion/familia.md).
        Route::get('me/tracking/sharing', [TrackingSharingController::class, 'overview'])->name('me.tracking.sharing');
        Route::get('tracking/sessions/{session:uuid}/sharing', [TrackingSharingController::class, 'show'])->name('tracking.sessions.sharing.show');
        Route::post('tracking/sessions/{session:uuid}/sharing', [TrackingSharingController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('tracking.sessions.sharing.store');
        Route::delete('tracking/sessions/{session:uuid}/sharing', [TrackingSharingController::class, 'destroy'])->name('tracking.sessions.sharing.destroy');

        // Familia y contactos autorizados.
        Route::prefix('family')->name('family.')->group(function () {
            Route::get('/', [FamilyLinkController::class, 'index'])->name('index');
            Route::post('links', [FamilyLinkController::class, 'store'])->middleware('throttle:10,1')->name('links.store');
            Route::patch('links/{link:uuid}', [FamilyLinkController::class, 'update'])->middleware('throttle:30,1')->name('links.update');
            Route::delete('links/{link:uuid}', [FamilyLinkController::class, 'destroy'])->name('links.destroy');
            Route::post('links/{link:uuid}/accept', [FamilyLinkController::class, 'accept'])->middleware('throttle:20,1')->name('links.accept');
            Route::post('links/{link:uuid}/decline', [FamilyLinkController::class, 'decline'])->middleware('throttle:20,1')->name('links.decline');

            Route::middleware('throttle:20,1')->where(['token' => '[A-Za-z0-9]{20,128}'])->group(function () {
                Route::get('invites/{token}', [FamilyLinkController::class, 'preview'])->name('invites.show');
                Route::post('invites/{token}/accept', [FamilyLinkController::class, 'acceptToken'])->name('invites.accept');
                Route::post('invites/{token}/decline', [FamilyLinkController::class, 'declineToken'])->name('invites.decline');
            });

            Route::get('following', [FamilyFollowingController::class, 'index'])->name('following.index');
            Route::get('following/{share}', [FamilyFollowingController::class, 'show'])
                ->whereUuid('share')
                ->middleware('throttle:live-view')
                ->name('following.show');
        });
    });
});
