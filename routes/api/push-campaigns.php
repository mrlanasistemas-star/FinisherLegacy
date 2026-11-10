<?php

use App\Http\Controllers\Api\V1\NotificationOpenedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1 push opens (docs/renovacion/campanas-push.md §Aperturas)
|--------------------------------------------------------------------------
| The app reports a tapped push: {id} = data.campaign_id (campaign uuid)
| or the notification-center id. Only the caller's own row is touched.
*/
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::post('notifications/{id}/opened', NotificationOpenedController::class)
        ->whereUuid('id')
        ->middleware('throttle:60,1')
        ->name('notifications.opened');
});
