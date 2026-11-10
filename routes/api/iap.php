<?php

use App\Http\Controllers\Api\V1\Iap\IapPurchaseController;
use App\Http\Controllers\Api\Webhooks\AppStoreNotificationController;
use App\Http\Controllers\Api\Webhooks\GooglePlayNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| In-app purchases of photo expansions (docs/renovacion/compras-dentro-de-app.md)
|--------------------------------------------------------------------------
| StoreKit 2 / Google Play Billing. The app asks which store product to
| load for a story, buys it with the store SDK and posts the transaction
| here; the backend verifies it with Apple / Google before granting.
| Store notifications (refunds) are public routes authenticated by the
| store's own signature / Pub/Sub OIDC token.
*/
Route::prefix('v1/iap')->name('api.v1.iap.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::get('offer', [IapPurchaseController::class, 'offer'])
        ->middleware('throttle:60,1')
        ->name('offer');

    Route::post('purchases', [IapPurchaseController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('purchases.store');
});

Route::post('webhooks/app-store', AppStoreNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('api.webhooks.app-store');

Route::post('webhooks/google-play', GooglePlayNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('api.webhooks.google-play');
