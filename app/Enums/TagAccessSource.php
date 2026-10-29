<?php

namespace App\Enums;

/**
 * Por dónde se abrió un tag: chip NFC, código escrito o enlace compartido.
 * `Qr` solo existe para enlaces antiguos (`?src=qr`): el tag ya no lleva QR impreso.
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
            self::Qr => 'Enlace antiguo (QR)',
            self::Code => 'Código escrito',
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
