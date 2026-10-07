<?php

namespace App\Enums;

/**
 * The whitelist of dynamic fields a Legacy Plate layout may print — no
 * arbitrary decoration. Legacy Plate V3 prints on the FRONT only (the back
 * carries the money clip) and carries an NFC chip, so there is no printed
 * QR: `Qr` only survives so historical Plate snapshots still deserialize;
 * it is never offered by the layout editor (see selectable()).
 */
enum LegacyPlateFieldKey: string
{
    case AthleteName = 'athlete_name';
    case RaceLabel = 'race_label';
    case OfficialTime = 'official_time';
    case Pace = 'pace';
    case EventName = 'event_name';
    case EventDate = 'event_date';
    case Distance = 'distance';
    case OverallPosition = 'overall_position';
    case BibNumber = 'bib_number';
    /** @deprecated Plates are NFC-only now; kept for historical data. */
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::AthleteName => 'Nombre del atleta',
            self::RaceLabel => 'Carrera / distancia',
            self::OfficialTime => 'Tiempo oficial',
            self::Pace => 'Ritmo',
            self::EventName => 'Nombre del evento',
            self::EventDate => 'Fecha del evento',
            self::Distance => 'Distancia',
            self::OverallPosition => 'Posición general',
            self::BibNumber => 'Número de corredor',
            self::Qr => 'QR (retirado)',
        };
    }

    /**
     * Which face a field lives on. Always the front since V3 — the back is
     * the clip. Historical v2 plates keep their back fields in their own
     * layout_snapshot.
     */
    public function defaultFace(): string
    {
        return 'front';
    }

    /**
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $key) => $key !== self::Qr));
    }
}
