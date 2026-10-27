<?php

namespace App\Enums;

/**
 * Estado de una incidencia.
 */
enum SupportCaseStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::InProgress => 'En proceso',
            self::Resolved => 'Resuelta',
            self::Closed => 'Cerrada',
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
