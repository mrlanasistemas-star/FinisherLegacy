<?php

use App\Http\Controllers\Api\V1\NfcController;
use App\Support\Nfc\NfcToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NFC API (docs/NFC.md §API) — /api/v1/nfc/{token}
|--------------------------------------------------------------------------
| Public resolve (an optional Bearer token personalises owned_by_me /
| can_claim) and authenticated claim. Deep link: finisherlegacy://nfc/{token}.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('nfc/{token}', [NfcController::class, 'show'])
        ->where('token', NfcToken::PATTERN)
        ->middleware('throttle:nfc-resolve')
        ->name('nfc.show');

    Route::post('nfc/{token}/claim', [NfcController::class, 'claim'])
        ->where('token', NfcToken::PATTERN)
        ->middleware(['auth:sanctum', 'user.token', 'throttle:api-claim'])
        ->name('nfc.claim');
});
