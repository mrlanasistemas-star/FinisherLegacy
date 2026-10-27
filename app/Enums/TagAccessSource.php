<?php

namespace App\Enums;

/**
 * Por dónde se abrió un tag: chip NFC, QR de respaldo, código escrito o enlace compartido.
 */
enum TagAccessSource: string
{
    case Nfc = 'nfc';
    case Qr = 'qr';
    case Code = 'code';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Nfc => 'NFC',
            self::Qr => 'QR',
            self::Code => 'Legacy Code',
            self::Link => 'Enlace',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
