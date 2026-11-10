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
            self::ActivationHelp => __('Ayuda con activación'),
            self::NfcFailure => __('Falla NFC'),
            self::QrFailure => __('Falla QR (antiguo)'),
            self::Defect => __('Defecto'),
            self::Return => __('Devolución'),
            self::Replacement => __('Reemplazo'),
            self::Ownership => __('Propiedad / reclamo'),
            self::Other => __('Otro'),
        };
    }

    /**
     * Options for new cases. `QrFailure` stays only so old cases keep their
     * label — the tag no longer carries a printed QR.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $cases = array_filter(self::cases(), fn (self $c) => $c !== self::QrFailure);

        return array_values(array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], $cases));
    }
}
