<?php

use App\Http\Controllers\Api\V1\QuotaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1 photo quotas + purchased rights (docs/renovacion/cuotas-y-precios.md)
|--------------------------------------------------------------------------
| Read-only. Story quota is owner-only (404 otherwise).
*/
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::get('stories/{publicId}/quota', [QuotaController::class, 'story'])
        ->where('publicId', '[a-z0-9]{8,16}')
        ->name('stories.quota');

    Route::get('me/entitlements', [QuotaController::class, 'entitlements'])
        ->name('me.entitlements.index');
});
