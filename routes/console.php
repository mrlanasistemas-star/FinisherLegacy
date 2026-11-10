<?php

use App\Actions\Notifications\AlertIfPaymentGatewayUnconfigured;
use App\Actions\Notifications\CampaignDispatcher;
use App\Console\Commands\ExpirePendingOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Consolidation brief §18-§19 — every 5 minutes is frequent enough that a
// pending Order never holds its reservation much past the configured
// window, without needing per-second precision.
Schedule::command(ExpirePendingOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Expired person API tokens (SANCTUM_EXPIRATION_MINUTES, see
// config/sanctum.php) are rejected already; this just deletes the rows.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();

// Synology/SMB media mount probe (docs/renovacion/synology-smb.md): logs
// `storage.health_failed` and exits non-zero when the mount is missing,
// read-only or low on space.
Schedule::command('storage:health')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Campañas push programadas (docs/renovacion/campanas-push.md): starts every
// scheduled campaign whose time has come. Starting is atomic, so an
// overlapping run or "Enviar ahora" can never send one twice.
Artisan::command('push-campaigns:dispatch', function (CampaignDispatcher $dispatcher) {
    $started = $dispatcher->dispatchDue();
    $this->info("Campañas iniciadas: {$started}");
})->purpose('Inicia las campañas push programadas cuya hora ya llegó');

Schedule::command('push-campaigns:dispatch')
    ->everyMinute()
    ->withoutOverlapping();

// Expo push receipts (docs/renovacion/notificaciones.md): deactivates tokens
// whose receipt says DeviceNotRegistered and prunes old tickets.
Schedule::command('push:check-receipts')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Admin alerts — Inventario digest (per-variant alerts fire on every stock
// movement) and Pagos: OpenPay active but not configured.
Schedule::command('inventory:check-low-stock')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// Planes por periodo de las ampliaciones (docs/renovacion/cuotas-y-precios.md):
// avisa 30, 7 y 1 día antes de que venza un plan y cuando ya venció.
Schedule::command('quotas:remind-expiring')
    ->dailyAt('10:00')
    ->withoutOverlapping();

Schedule::call(fn () => app(AlertIfPaymentGatewayUnconfigured::class)->handle())
    ->name('admin-alerts:payment-gateway')
    ->dailyAt('08:05')
    ->withoutOverlapping();
