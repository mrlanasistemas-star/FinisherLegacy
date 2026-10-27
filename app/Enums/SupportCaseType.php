<?php

namespace App\Enums;

/**
 * Motivo de una incidencia de soporte.
 */
enum SupportCaseType: string
{
    case ActivationHelp = 'activation_help';
    case NfcFailure = 'nfc_failure';
    case QrFailure = 'qr_failure';
    case Defect = 'defect';
    case Return = 'return';
    case Replacement = 'replacement';
    case Ownership = 'ownership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ActivationHelp => 'Ayuda con activación',
            self::NfcFailure => 'Falla NFC',
            self::QrFailure => 'Falla QR',
            self::Defect => 'Defecto',
            self::Return => 'Devolución',
            self::Replacement => 'Reemplazo',
            self::Ownership => 'Propiedad / reclamo',
            self::Other => 'Otro',
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
