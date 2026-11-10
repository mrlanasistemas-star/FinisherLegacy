<?php

use App\Http\Controllers\Admin\AdminAlertController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
| Notificaciones (docs/renovacion/notificaciones.md):
|  - Ajustes › Notificaciones: per-category in-app + push switches (same
|    storage as the app, GET|PUT /api/v1/me/notifications/preferences).
|  - Administración › Alertas: the admin topbar bell (summary/read), the
|    full list and each admin's email/push choices per category.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('settings/notificaciones', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notificaciones', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
});

Route::middleware(['auth', 'verified', 'can:dashboard.admin.view'])
    ->prefix('admin/alertas')
    ->name('admin.alerts.')
    ->group(function () {
        Route::get('/', [AdminAlertController::class, 'index'])->name('index');
        Route::get('resumen', [AdminAlertController::class, 'summary'])->middleware('throttle:120,1')->name('summary');
        Route::post('leer-todas', [AdminAlertController::class, 'markAllRead'])->name('read-all');
        Route::get('preferencias', [AdminAlertController::class, 'preferences'])->name('preferences');
        Route::put('preferencias', [AdminAlertController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('{alert}/leer', [AdminAlertController::class, 'markRead'])->whereUuid('alert')->name('read');
    });
