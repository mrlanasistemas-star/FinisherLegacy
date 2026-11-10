<?php

/*
|--------------------------------------------------------------------------
| Administración → Contenido → Entrenamientos (moderación de «Envíame ánimo»)
|--------------------------------------------------------------------------
| docs/renovacion/entrenamientos.md §Moderación. Aggregates only; a single
| message (and its audio, via a short signed URL, audited) is reviewable
| only after the athlete reported it. Gated by `community.moderate`.
*/

use App\Http\Controllers\Admin\Training\CheerModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:dashboard.admin.view', 'can:community.moderate'])
    ->prefix('admin/entrenamientos')
    ->name('admin.training.')
    ->group(function () {
        Route::get('/', [CheerModerationController::class, 'index'])->name('index');

        Route::post('enlaces/{link:uuid}/desactivar', [CheerModerationController::class, 'disableLink'])->name('links.disable');
        Route::post('enlaces/{link:uuid}/reactivar', [CheerModerationController::class, 'enableLink'])->name('links.enable');

        Route::patch('reportes/{report:uuid}', [CheerModerationController::class, 'updateReport'])->name('reports.update');
        Route::post('reportes/{report:uuid}/purgar', [CheerModerationController::class, 'purge'])->name('reports.purge');
        Route::post('reportes/{report:uuid}/audio', [CheerModerationController::class, 'audio'])->middleware('throttle:30,1')->name('reports.audio');
    });
