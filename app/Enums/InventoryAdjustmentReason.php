<?php

namespace App\Enums;

/**
 * Why stock changed by hand (spec §14: "adjustment → audited movement").
 * Stored on InventoryMovement.reason next to the user and free notes.
 */
enum InventoryAdjustmentReason: string
{
    case Receipt = 'receipt';
    case Count = 'count';
    case Damage = 'damage';
    case Correction = 'correction';
    case Return = 'return';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Entrada de mercancía',
            self::Count => 'Conteo físico',
            self::Damage => 'Merma o daño',
            self::Correction => 'Corrección',
            self::Return => 'Devolución',
            self::Other => 'Otro',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
