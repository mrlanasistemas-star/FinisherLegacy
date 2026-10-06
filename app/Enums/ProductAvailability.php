<?php

namespace App\Enums;

/**
 * Commercial availability of a published product — separate from
 * ProductStatus (draft/active/archived = is it published at all). Only
 * `Available` can be added to a cart (enforced in AddCartItem); the other
 * two are catalog-only so the full product vision can be shown to
 * athletes, partners and suppliers without selling what isn't ready.
 */
enum ProductAvailability: string
{
    case Available = 'available';
    case ComingSoon = 'coming_soon';
    case Concept = 'concept';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::ComingSoon => 'Próximamente',
            self::Concept => 'Concepto',
        };
    }

    public function isPurchasable(): bool
    {
        return $this === self::Available;
    }
}
