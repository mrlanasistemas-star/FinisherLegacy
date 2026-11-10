<?php

namespace App\Jobs;

use App\Support\Notifications\LowStockMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Low/out-of-stock check for one variant after a stock movement (queued by
 * App\Listeners\AdminAlerts\ModelAlerts). Unique per variant for a minute,
 * so a burst of movements (a 50-item order) runs the check once.
 */
class CheckVariantStockJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $productVariantId) {}

    public function uniqueId(): string
    {
        return (string) $this->productVariantId;
    }

    public function handle(LowStockMonitor $monitor): void
    {
        $monitor->checkVariant($this->productVariantId);
    }
}
