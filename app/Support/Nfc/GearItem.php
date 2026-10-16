<?php

namespace App\Support\Nfc;

use App\Models\AthleteOwnedProduct;
use App\Models\EventGearSelection;
use App\Models\Plate;
use App\Queries\Nfc\ResolveNfcTag;

/**
 * One "Mi equipo" item — the single shape behind `/api/v1/me/gear`, the
 * `POST /api/v1/nfc/{token}/claim` response and the web Mi equipo page
 * (docs/NFC.md §Mi equipo). The original AthleteOwnedProductResource keys
 * are kept verbatim (backward compatible); everything else is additive.
 */
final class GearItem
{
    public const string CONNECTED_LABEL = 'Producto conectado';

    /**
     * @return array<string, mixed>
     */
    public static function fromOwnedProduct(AthleteOwnedProduct $owned): array
    {
        $nfc = $owned->relationLoaded('activeNfcAssignment') ? $owned->activeNfcAssignment : null;
        $connected = $nfc !== null && $nfc->isClaimed();
        $selections = $owned->relationLoaded('gearSelections') ? $owned->gearSelections->sortByDesc('selected_at')->values() : null;
        $latest = $selections?->first();
        $edition = $latest?->eventParticipant?->eventEdition;

        return [
            'uuid' => $owned->uuid,
            'kind' => 'product',
            'product_name' => $owned->product->name,
            'variant_name' => $owned->productVariant?->name,
            'status' => $owned->status->value,
            'asset_code' => $owned->asset_code,
            'acquired_at' => $owned->acquired_at->toIso8601String(),
            'activated_at' => $owned->activated_at?->toIso8601String(),
            'usage_history' => $selections?->map(fn (EventGearSelection $selection) => [
                'event_participant_id' => $selection->event_participant_id,
                'event' => $selection->eventParticipant->eventEdition?->event?->name,
                'edition' => $selection->eventParticipant->eventEdition?->name,
                'selected_at' => $selection->selected_at->toIso8601String(),
            ])->all() ?? [],
            'connected' => $connected,
            'connection_label' => $connected ? self::CONNECTED_LABEL : null,
            'connected_at' => $connected ? $nfc->activated_at?->toIso8601String() : null,
            'image_url' => $owned->product->primaryImageUrl(),
            'story_url' => $latest !== null ? route('dashboard.legado.show', $latest->event_participant_id) : null,
            'event' => $edition !== null ? [
                'name' => $edition->event?->name,
                'edition' => $edition->name,
                'slug' => $edition->event?->slug,
                'date' => $edition->event_date->toDateString(),
                'race' => null,
                'official_time' => null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromPlate(Plate $plate): array
    {
        $nfc = $plate->relationLoaded('activeNfcAssignment') ? $plate->activeNfcAssignment : null;
        $connected = $nfc !== null && $nfc->isClaimed();
        $code = $plate->legacyCode;
        $edition = $plate->eventEdition;

        return [
            'uuid' => $code->uuid ?? 'plate-'.$plate->serial_number,
            'kind' => 'legacy_plate',
            'product_name' => 'Legacy Plate',
            'variant_name' => $plate->event_name,
            'status' => 'active',
            'asset_code' => null,
            'acquired_at' => ($plate->linked_at ?? $plate->created_at)->toIso8601String(),
            'activated_at' => $code?->claimed_at?->toIso8601String(),
            'usage_history' => [],
            'connected' => $connected,
            'connection_label' => $connected ? self::CONNECTED_LABEL : null,
            'connected_at' => $connected ? $nfc->activated_at?->toIso8601String() : null,
            'image_url' => asset(ResolveNfcTag::PLATE_IMAGE),
            'story_url' => $code !== null ? route('legacy-code.show', $code->code) : null,
            'event' => [
                'name' => $edition?->event->name ?? $plate->event_name,
                'edition' => $edition?->name,
                'slug' => $edition?->event?->slug,
                'date' => $plate->event_date?->toDateString(),
                'race' => $plate->race_name,
                'official_time' => $plate->official_time,
            ],
        ];
    }
}
