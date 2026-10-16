<?php

use App\Http\Controllers\Admin\EditionController as AdminEditionController;
use App\Http\Controllers\Admin\EventPhotoController as AdminEventPhotoController;
use App\Http\Controllers\Admin\EventWorkspaceController as AdminEventWorkspaceController;
use App\Http\Controllers\Admin\GlobalSearchController as AdminGlobalSearchController;
use App\Http\Controllers\Admin\ParticipantController as AdminParticipantController;
use App\Http\Controllers\Admin\PreregistrationController as AdminPreregistrationController;
use Illuminate\Support\Facades\Route;

/*
| Admin shell + events workspace (spec §8-§9, §111; docs/MODULES.md).
| /admin/events/{eventEdition} is the event workspace: Resumen,
| Participantes, Resultados, Prerregistros, Datos, Tracking (A8) and Media.
| The retired /admin/editions URLs 301 here (routes/web.php).
*/
Route::middleware(['auth', 'verified', 'can:dashboard.admin.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('search', AdminGlobalSearchController::class)->middleware('throttle:60,1')->name('search');

    Route::middleware('can:events.view')->get('events', [AdminEditionController::class, 'index'])->name('events.index');
    Route::middleware('can:events.manage')->get('events/create', [AdminEditionController::class, 'create'])->name('events.create');

    Route::prefix('events/{eventEdition}')->whereNumber('eventEdition')->name('events.')->group(function () {
        Route::middleware('can:events.view')->get('/', [AdminEditionController::class, 'show'])->name('show');
        Route::middleware('can:participants.view')->get('participants', [AdminParticipantController::class, 'forEvent'])->name('participants');
        Route::middleware('can:participants.view')->get('results', [AdminEventWorkspaceController::class, 'results'])->name('results');
        Route::middleware('can:preregistrations.view')->get('preregistrations', [AdminPreregistrationController::class, 'forEvent'])->name('preregistrations');
        Route::middleware('can:events.view')->get('data', [AdminEventWorkspaceController::class, 'data'])->name('data');
        Route::middleware('can:photos.manage')->get('media', [AdminEventPhotoController::class, 'forEvent'])->name('media');
    });
});
