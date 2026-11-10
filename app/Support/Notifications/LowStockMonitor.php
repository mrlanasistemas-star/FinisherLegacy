<?php

namespace App\Support\Notifications;

use App\Actions\Notifications\NotifyAdmins;
use App\Enums\AdminAlertCategory;
use App\Models\ProductVariant;
use App\Queries\Commerce\InventoryStock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Inventario alerts for stock (docs/renovacion/notificaciones.md §Stock):
 * "available" (on hand − reserved, at the checkout location) against the
 * restock threshold `minimum_stock` (variant, else product, else
 * finisher.commerce.default_minimum_stock) — the same numbers and rule as
 * the admin Inventario screen (App\Queries\Commerce\InventoryStock).
 *
 *  - `checkVariant()` runs after every stock movement (queued): one alert
 *    when a variant goes LOW, one when it runs OUT; each fires at most once
 *    per THROTTLE_HOURS and re-arms as soon as stock recovers.
 *  - `digest()` runs on the schedule: a single summary alert per day when
 *    anything is low/out, never one alert per variant.
 */
class LowStockMonitor
{
    public const int THROTTLE_HOURS = 24;

    public function __construct(
        private readonly InventoryStock $stock,
        private readonly NotifyAdmins $notify,
    ) {}

    /**
     * @return 'ok'|'low'|'out'|null null = untracked variant
     */
    public function checkVariant(int $variantId): ?string
    {
        /** @var (ProductVariant&object{stock_available: int|string, stock_minimum: int|string, product_name: string})|null $row */
        $row = $this->stock->query()->where('product_variants.id', $variantId)->first();

        if ($row === null) {
            return null;
        }

        $available = (int) $row->stock_available;
        $minimum = (int) $row->stock_minimum;
        $state = $available <= 0 ? 'out' : ($available <= $minimum ? 'low' : 'ok');

        if ($state === 'ok') {
            Cache::forget(NotifyAdmins::throttleCacheKey(AdminAlertCategory::Inventario, "stock:{$variantId}:low"));
            Cache::forget(NotifyAdmins::throttleCacheKey(AdminAlertCategory::Inventario, "stock:{$variantId}:out"));

            return $state;
        }

        $name = trim($row->product_name.' '.($row->name ? '· '.$row->name : ''));

        $this->notify->handle(
            AdminAlertCategory::Inventario,
            $state === 'out' ? 'out_of_stock' : 'low_stock',
            $state === 'out' ? 'Producto agotado' : 'Stock bajo',
            $state === 'out'
                ? "{$name} se agotó (mínimo {$minimum})."
                : "{$name}: quedan {$available} disponibles (mínimo {$minimum}).",
            $this->url($state),
            ['product_variant_id' => $variantId, 'available' => $available, 'minimum' => $minimum],
            throttleKey: "stock:{$variantId}:{$state}",
            throttleMinutes: self::THROTTLE_HOURS * 60,
            level: $state === 'out' ? 'danger' : 'warning',
        );

        return $state;
    }

    /**
     * @return array{low: int, out: int}
     */
    public function digest(): array
    {
        $counts = $this->stock->counts();
        $low = (int) $counts['low'];
        $out = (int) $counts['out'];

        if ($low + $out > 0) {
            $parts = array_filter([
                $out > 0 ? "{$out} ".($out === 1 ? 'variante agotada' : 'variantes agotadas') : null,
                $low > 0 ? "{$low} con stock bajo" : null,
            ]);

            $this->notify->handle(
                AdminAlertCategory::Inventario,
                'stock_digest',
                'Resumen de inventario',
                ucfirst(implode(' y ', $parts)).'. Revisa y reabastece.',
                $this->url($out > 0 ? 'out' : 'low'),
                ['low' => $low, 'out' => $out],
                throttleKey: 'stock:digest',
                throttleMinutes: 20 * 60,
                level: $out > 0 ? 'danger' : 'warning',
            );
        }

        return ['low' => $low, 'out' => $out];
    }

    private function url(string $state): string
    {
        return Route::has('admin.commerce.inventory.index')
            ? route('admin.commerce.inventory.index', ['estado' => $state], absolute: false)
            : '/admin';
    }
}
