<?php

namespace App\Enums;

enum PhotographerStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('En revisión'),
            self::Approved => __('Aprobado'),
            self::Suspended => __('Suspendido'),
        };
    }
}
