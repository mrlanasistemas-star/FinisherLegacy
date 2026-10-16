<?php

use App\Http\Controllers\Api\V1\Social\CommunityMetaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Comunidad v1 API additions (spec §86–87). Existing social endpoints
| (feed, moments, comments, reactions, follow, followers/following,
| block, reports) stay in routes/api.php unchanged; `GET /feed` now also
| accepts `scope=for_you|events`.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'user.token'])->group(function () {
    Route::get('community/meta', CommunityMetaController::class)->name('community.meta');
});
