<?php

namespace App\Enums;

use App\Models\ProductionJob;

/**
 * "Preparación y entrega" (docs/NFC.md §Preparación) — the six stages staff
 * see, derived from the existing ProductionJob state machine plus whether
 * the plate already has an NFC tag. This is a DISPLAY mapping only: the
 * device state machine (docs/adr/0003) is untouched and the external machine
 * software keeps driving it through the Device API.
 */
enum PreparationStage: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Personalized = 'personalized';
    case NfcAssigned = 'nfc_assigned';
    case Ready = 'ready';
    case Delivered = 'delivered';
    /** Failed/cancelled attempts — shown apart, never one of the six lanes. */
    case Attention = 'attention';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Preparing => 'Preparando',
            self::Personalized => 'Personalizada',
            self::NfcAssigned => 'NFC asignado',
            self::Ready => 'Lista',
            self::Delivered => 'Entregada',
            self::Attention => 'Requiere atención',
        };
    }

    /**
     * @return list<self>
     */
    public static function lanes(): array
    {
        return [self::Pending, self::Preparing, self::Personalized, self::NfcAssigned, self::Ready, self::Delivered];
    }

    public static function fromJob(?ProductionJob $job, bool $hasNfc): self
    {
        if ($job === null) {
            return self::Pending;
        }

        return match ($job->status) {
            ProductionJobStatus::Queued => self::Pending,
            ProductionJobStatus::Assigned,
            ProductionJobStatus::Preparing,
            ProductionJobStatus::EngravingFront => self::Preparing,
            // The face is done — personalised. The flip/back/verify hops are
            // machine-software details of the historical two-face pipeline.
            ProductionJobStatus::AwaitingFlip,
            ProductionJobStatus::EngravingBack,
            ProductionJobStatus::VerifyingQr => $hasNfc ? self::NfcAssigned : self::Personalized,
            // A plate is only "Lista" once its NFC tag is attached; a job the
            // machine software already closed without one still needs it.
            ProductionJobStatus::Ready => $hasNfc ? self::Ready : self::Personalized,
            ProductionJobStatus::Delivered => self::Delivered,
            ProductionJobStatus::Failed,
            ProductionJobStatus::Cancelled => self::Attention,
        };
    }
}
