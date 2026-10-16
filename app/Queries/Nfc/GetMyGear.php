<?php

namespace App\Queries\Nfc;

use App\Enums\PlateStatus;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\Plate;
use App\Support\Nfc\GearItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Mi equipo" read model: the athlete's physical products plus the Legacy
 * Plates that are already part of their Legacy (claimed Legacy Code or an
 * NFC tag connected to them). Used by the web page, `/api/v1/me/gear` and
 * the NFC claim response — one shape, one query (docs/NFC.md §Mi equipo).
 */
class GetMyGear
{
    /** Hard cap per kind — a closet, never an unbounded list. */
    private const int LIMIT = 200;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Athlete $athlete): array
    {
        $products = $athlete->ownedProducts()
            ->with([
                'product.media', 'productVariant', 'activeNfcAssignment',
                'gearSelections.eventParticipant.eventEdition.event',
            ])
            ->orderByDesc('acquired_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AthleteOwnedProduct $owned) => GearItem::fromOwnedProduct($owned));

        $plates = Plate::query()
            ->where('athlete_id', $athlete->id)
            ->whereNotIn('status', [PlateStatus::Cancelled, PlateStatus::Draft])
            ->where(fn (Builder $q) => $q
                ->whereHas('legacyCode', fn (Builder $c) => $c->whereNotNull('claimed_by_user_id'))
                ->orWhereHas('activeNfcAssignment', fn (Builder $a) => $a->where('athlete_id', $athlete->id)->whereNotNull('activated_at')))
            ->with(['legacyCode', 'eventEdition.event', 'activeNfcAssignment'])
            ->orderByDesc('event_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Plate $plate) => GearItem::fromPlate($plate));

        return $plates->concat($products)
            ->sortByDesc(fn (array $item) => $item['connected_at'] ?? $item['acquired_at'])
            ->values()
            ->all();
    }

    /**
     * The single item a claim just produced, in the same shape.
     *
     * @return array<string, mixed>|null
     */
    public function item(Athlete $athlete, ?int $plateId, ?int $ownedProductId): ?array
    {
        if ($plateId !== null) {
            $plate = Plate::query()->whereKey($plateId)->with(['legacyCode', 'eventEdition.event', 'activeNfcAssignment'])->first();

            return $plate !== null ? GearItem::fromPlate($plate) : null;
        }

        if ($ownedProductId !== null) {
            $owned = $athlete->ownedProducts()
                ->whereKey($ownedProductId)
                ->with(['product.media', 'productVariant', 'activeNfcAssignment', 'gearSelections.eventParticipant.eventEdition.event'])
                ->first();

            return $owned !== null ? GearItem::fromOwnedProduct($owned) : null;
        }

        return null;
    }
}
