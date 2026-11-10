<?php

namespace App\Enums;

/**
 * Canal de venta conservado en cada pedido y cada unidad vendida (docs/architecture/commerce-os.md §Canales).
 */
enum SalesChannel: string
{
    case Direct = 'direct';
    case Ally = 'ally';
    case OwnEvent = 'own_event';
    case Online = 'online';
    // In-app purchases (digital goods only) — docs/renovacion/compras-dentro-de-app.md.
    case AppStore = 'app_store';
    case PlayStore = 'play_store';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Venta directa',
            self::Ally => 'Aliado',
            self::OwnEvent => 'Presencia propia',
            self::Online => 'E-commerce',
            self::AppStore => 'App Store',
            self::PlayStore => 'Google Play',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    /**
     * Apple / Google sell at a store-managed price tier: the store decides the
     * amount, so these channels never take a manual per-channel price.
     */
    public function isAppStore(): bool
    {
        return $this === self::AppStore || $this === self::PlayStore;
    }

    /**
     * Channels an admin can set a manual price for (everything but the app stores).
     *
     * @return list<self>
     */
    public static function manuallyPriced(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => ! $c->isAppStore()));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function manualPricingOptions(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::manuallyPriced());
    }
}
