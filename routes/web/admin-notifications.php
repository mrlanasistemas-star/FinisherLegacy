<?php

use App\Http\Controllers\Admin\Notifications\PushCampaignController;
use App\Http\Controllers\Admin\Notifications\PushCampaignLookupController;
use Illuminate\Support\Facades\Route;

/*
| Notificaciones → Campañas push (docs/renovacion/campanas-push.md):
| compose, schedule, send, cancel, duplicate and review push campaigns.
| Gated by `notifications.broadcast`. The scheduled ones are started by
| `push-campaigns:dispatch` (routes/console.php, every minute).
*/
Route::middleware(['auth', 'verified', 'can:dashboard.admin.view', 'can:notifications.broadcast'])
    ->prefix('admin/notificaciones/campanas')
    ->name('admin.push-campaigns.')
    ->group(function () {
        Route::get('/', [PushCampaignController::class, 'index'])->name('index');
        Route::get('crear', [PushCampaignController::class, 'create'])->name('create');
        Route::post('/', [PushCampaignController::class, 'store'])->middleware('throttle:30,1')->name('store');

        Route::get('alcance', [PushCampaignLookupController::class, 'estimate'])->middleware('throttle:120,1')->name('estimate');
        Route::get('buscar', [PushCampaignLookupController::class, 'search'])->middleware('throttle:120,1')->name('search');

        Route::get('{campaign}', [PushCampaignController::class, 'show'])->whereUuid('campaign')->name('show');
        Route::get('{campaign}/editar', [PushCampaignController::class, 'edit'])->whereUuid('campaign')->name('edit');
        // POST (not PUT) so the composer can send the image as multipart.
        Route::post('{campaign}', [PushCampaignController::class, 'update'])->whereUuid('campaign')->middleware('throttle:30,1')->name('update');
        Route::post('{campaign}/cancelar', [PushCampaignController::class, 'cancel'])->whereUuid('campaign')->name('cancel');
        Route::post('{campaign}/borrador', [PushCampaignController::class, 'unschedule'])->whereUuid('campaign')->name('unschedule');
        Route::post('{campaign}/duplicar', [PushCampaignController::class, 'duplicate'])->whereUuid('campaign')->name('duplicate');
    });
