<?php

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
