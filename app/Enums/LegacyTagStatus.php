<?php

namespace App\Enums;

/**
 * Lifecycle of one physical Finisher Legacy Tag (docs/architecture/commerce-os.md §Tag).
 *
 *   manufactured → in_stock ⇄ reserved → sold → delivered → activated
 *                                   ↘ returned / replaced / disabled (any time, staff only)
 *
 * The product/SKU is ProductVariant; this is the individual unit with its own
 * public code and NFC chip. Only staff Actions move a unit between states —
 * never a controller and never the public, except activation itself.
 */
enum LegacyTagStatus: string
{
    /** Created from a lot (code generated, chip may be encoded) but not received yet. */
    case Manufactured = 'manufactured';

    /** Physically at an inventory location, sellable. */
    case InStock = 'in_stock';

    /** Held for a paid/pending online order or a POS sale in progress. */
    case Reserved = 'reserved';

    /** Sold (order or POS), not yet handed over / shipped. */
    case Sold = 'sold';

    /** In the buyer's hands — ready to activate. */
    case Delivered = 'delivered';

    /** Claimed by an athlete: opens their Legacy. */
    case Activated = 'activated';

    /** Superseded by a replacement unit (defect, loss); keeps its history. */
    case Replaced = 'replaced';

    /** Came back (refund/return); may be re-stocked by staff. */
    case Returned = 'returned';

    /** Revoked / lost / destroyed — opening it shows an honest page. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Manufactured => 'Fabricado',
            self::InStock => 'En inventario',
            self::Reserved => 'Reservado',
            self::Sold => 'Vendido',
            self::Delivered => 'Entregado',
            self::Activated => 'Activado',
            self::Replaced => 'Reemplazado',
            self::Returned => 'Devuelto',
            self::Disabled => 'Desactivado',
        };
    }

    /** Counts as physically sellable stock. */
    public function isSellable(): bool
    {
        return $this === self::InStock;
    }

    /** States from which the buyer may activate it. */
    public function isActivatable(): bool
    {
        return in_array($this, [self::Sold, self::Delivered], true);
    }

    /** States that mean "this unit has left our inventory with a customer". */
    public function isSold(): bool
    {
        return in_array($this, [self::Sold, self::Delivered, self::Activated], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Replaced, self::Disabled], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
