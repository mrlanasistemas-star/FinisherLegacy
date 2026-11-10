<?php

namespace App\Console\Commands;

use App\Support\Notifications\LowStockMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled inventory digest (routes/console.php): one "Resumen de
 * inventario" admin alert per day when any variant is low or out of stock.
 * Per-variant alerts already fire on every stock movement
 * (App\Jobs\CheckVariantStockJob); this catches thresholds changed by hand
 * and anything that slipped by. `--variant=ID` checks a single variant.
 */
#[Signature('inventory:check-low-stock {--variant= : Revisar sólo esta variante (id)}')]
#[Description('Avisa a los administradores de productos con stock bajo o agotados.')]
class CheckLowStock extends Command
{
    public function handle(LowStockMonitor $monitor): int
    {
        if (filled($this->option('variant'))) {
            $state = $monitor->checkVariant((int) $this->option('variant'));
            $this->info('Estado de la variante: '.($state ?? 'sin inventario'));

            return self::SUCCESS;
        }

        $result = $monitor->digest();
        $this->info("Stock bajo: {$result['low']}. Agotados: {$result['out']}.");

        return self::SUCCESS;
    }
}
