<?php

namespace App\Actions\Athletes;

use App\Exceptions\EventGearAlreadyAssignedException;
use App\Exceptions\EventGearOwnershipMismatchException;
use App\Models\AthleteOwnedProduct;
use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use Illuminate\Support\Str;

/**
 * Records what an Athlete actually used in a race (product consolidation
 * brief §16-§21) — deliberately never called automatically from
 * fulfillment; buying gear for an event doesn't mean it was used there.
 */
class AssignOwnedProductToEvent
{
    public function handle(EventParticipant $participant, AthleteOwnedProduct $ownedProduct, ?string $notes = null): EventGearSelection
    {
        if ($ownedProduct->athlete_id !== $participant->athlete_id) {
            throw new EventGearOwnershipMismatchException;
        }

        if (EventGearSelection::query()
            ->where('event_participant_id', $participant->id)
            ->where('athlete_owned_product_id', $ownedProduct->id)
            ->exists()
        ) {
            throw new EventGearAlreadyAssignedException;
        }

        $ownedProduct->loadMissing(['product', 'productVariant']);
        $variant = $ownedProduct->productVariant;

        return EventGearSelection::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $participant->athlete_id,
            'event_participant_id' => $participant->id,
            'athlete_owned_product_id' => $ownedProduct->id,
            'snapshot' => [
                'product_name' => $ownedProduct->product->name,
                'variant_name' => $variant?->name,
                'sku' => $variant !== null ? $variant->sku : $ownedProduct->serial_code,
                'attributes' => $variant?->attributes,
                'asset_code' => $ownedProduct->asset_code,
            ],
            'notes' => $notes,
            'selected_at' => now(),
        ]);
    }
}
