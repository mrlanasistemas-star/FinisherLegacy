<?php

namespace App\Support\Commerce;

use App\Actions\Commerce\IsVariantAvailableForCheckout;
use App\Enums\ProductType;
use App\Models\ProductVariant;

/**
 * Customer-facing stock signal for a variant (spec §19 "stock visual"):
 * in_stock | low | out plus the quantity-stepper limit (capped at 20, the
 * cart's own bound). Never a raw stock count (brief §176-§177). Also the
 * size/color/color_hex trio clients render as chips.
 */
final class VariantStockStatus
{
    /** Common apparel colour names → swatch, used when the variant has no `color_hex`. */
    private const array COLOR_HEX = [
        'negro' => '#171714', 'black' => '#171714',
        'blanco' => '#FFFFFF', 'white' => '#FFFFFF',
        'gris' => '#8A8A86', 'gray' => '#8A8A86', 'grey' => '#8A8A86', 'grafito' => '#3A3A37',
        'azul' => '#1F3A5F', 'blue' => '#1F3A5F', 'marino' => '#1B2A41', 'navy' => '#1B2A41',
        'rojo' => '#B3261E', 'red' => '#B3261E',
        'verde' => '#2F5D3A', 'green' => '#2F5D3A',
        'dorado' => '#C9A45C', 'oro' => '#C9A45C', 'gold' => '#C9A45C',
        'plata' => '#BFC3C7', 'silver' => '#BFC3C7', 'acero' => '#A7ABAF',
        'beige' => '#D9CBB0', 'arena' => '#D9CBB0', 'crema' => '#F1EAD8',
        'naranja' => '#D9692B', 'orange' => '#D9692B', 'amarillo' => '#E2B93B', 'yellow' => '#E2B93B',
        'rosa' => '#D98BA0', 'pink' => '#D98BA0', 'morado' => '#5B3F7A', 'purple' => '#5B3F7A',
    ];

    /**
     * @return array{stock_status: 'in_stock'|'low'|'out', max_quantity: int}
     */
    public static function for(ProductVariant $variant): array
    {
        $variant->loadMissing('product');
        $available = app(IsVariantAvailableForCheckout::class)->availableQuantity($variant);
        $isPlate = $variant->product->type === ProductType::LegacyPlate;

        if (! $variant->active) {
            return ['stock_status' => 'out', 'max_quantity' => 0];
        }

        if ($available === null) {
            return ['stock_status' => 'in_stock', 'max_quantity' => $isPlate ? 1 : 20];
        }

        $status = match (true) {
            $available <= 0 => 'out',
            $available <= $variant->minimumStock() => 'low',
            default => 'in_stock',
        };

        return ['stock_status' => $status, 'max_quantity' => $isPlate ? min(1, $available) : min(20, $available)];
    }

    /**
     * @return array{size: ?string, color: ?string, color_hex: ?string}
     */
    public static function swatch(ProductVariant $variant): array
    {
        $attrs = is_array($variant->getAttribute('attributes')) ? $variant->getAttribute('attributes') : [];
        ['size' => $size, 'color' => $color] = $variant->sizeAndColor();

        $hex = $attrs['color_hex'] ?? $attrs['hex'] ?? null;

        if (! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            $hex = $color !== null ? (self::COLOR_HEX[mb_strtolower(trim($color))] ?? null) : null;
        }

        return ['size' => $size, 'color' => $color, 'color_hex' => $hex];
    }
}
