<?php

namespace App\Support\Social;

/**
 * Human labels for config('finisher.social.report_reasons') — shared by
 * the web report dialog and admin Comunidad.
 */
final class ReportReasons
{
    private const array LABELS = [
        'spam' => 'Spam o publicidad',
        'harassment' => 'Acoso u ofensas',
        'inappropriate' => 'Contenido inapropiado',
        'impersonation' => 'Suplantación de identidad',
        'other' => 'Otro motivo',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (string $reason) => ['value' => $reason, 'label' => self::LABELS[$reason] ?? ucfirst($reason)],
            (array) config('finisher.social.report_reasons'),
        ));
    }
}
