<?php

use App\Http\Controllers\Admin\CommunityModerationController as AdminCommunityModerationController;
use App\Http\Controllers\CommunityController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Comunidad v1 (spec §52–59) — routes added on top of the existing
| community routes in routes/web.php (those keep their names/URIs).
|--------------------------------------------------------------------------
*/

// Fallback for the shared `community-write` limiter (A1 owns limiter
// definitions in AppServiceProvider). Only defined when missing. NOTE: with
// `route:cache` this file is not executed — keep the canonical definition
// in AppServiceProvider.
if (RateLimiter::limiter('community-write') === null) {
    RateLimiter::for('community-write', fn (Request $request) => [
        Limit::perMinute(30)->by('community-write:'.($request->user()?->id ?: $request->ip())),
        Limit::perHour(300)->by('community-write-h:'.($request->user()?->id ?: $request->ip())),
    ]);
}

// Followers / following of an athlete (privacy-checked in the controller).
Route::get('/@{athleteProfile:username}/{list}', [CommunityController::class, 'profileConnections'])
    ->whereIn('list', ['seguidores', 'siguiendo'])
    ->name('profile.connections');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('comunidad/reportes', [CommunityController::class, 'report'])
        ->middleware('throttle:reports')
        ->name('community.reports.store');

    Route::post('atletas/{athleteProfile:username}/bloquear', [CommunityController::class, 'block'])
        ->middleware('throttle:community-write')
        ->name('athletes.block');
    Route::delete('atletas/{athleteProfile:username}/bloquear', [CommunityController::class, 'unblock'])
        ->middleware('throttle:community-write')
        ->name('athletes.unblock');

    // Administración → Comunidad moderation actions (audited).
    Route::middleware(['can:dashboard.admin.view', 'can:community.moderate'])
        ->prefix('admin/community')
        ->name('admin.community.')
        ->group(function () {
            Route::post('moments/{moment}/hide', [AdminCommunityModerationController::class, 'hideMoment'])->name('moments.hide');
            Route::post('moments/{moment}/restore', [AdminCommunityModerationController::class, 'restoreMoment'])->name('moments.restore');
            Route::post('comments/{comment}/hide', [AdminCommunityModerationController::class, 'hideComment'])->name('comments.hide');
            Route::post('comments/{comment}/restore', [AdminCommunityModerationController::class, 'restoreComment'])->name('comments.restore');
        });
});
