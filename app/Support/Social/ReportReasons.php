<?php

namespace App\Support\Social;

/**
 * Human labels for config('finisher.social.report_reasons') — shared by
 * the web report dialog and admin Comunidad.
 */
final class ReportReasons
{
    private static function label(string $reason): string
    {
        return match ($reason) {
            'spam' => __('Spam o publicidad'),
            'harassment' => __('Acoso u ofensas'),
            'inappropriate' => __('Contenido inapropiado'),
            'impersonation' => __('Suplantación de identidad'),
            'other' => __('Otro motivo'),
            default => ucfirst($reason),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (string $reason) => ['value' => $reason, 'label' => self::label($reason)],
            (array) config('finisher.social.report_reasons'),
        ));
    }
}
