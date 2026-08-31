<?php

namespace App\Actions\LegacyPlates;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\LegacyPlateEntitlement;
use Illuminate\Support\Str;

/**
 * The only place a LegacyPlateEntitlement is created — from a presale
 * (before a bib exists), a checkout OrderItem, or an event-day/manual sale
 * (brief §79-§83). Never infers payment state from a Product name; the
 * caller (CheckoutCart, RegisterManualPayment, ...) decides the initial
 * status explicitly.
 */
class CreateLegacyPlateEntitlement
{
    /**
     * @param  array{athlete_id?: ?int, event_edition_id: int, event_participant_id?: ?int, legacy_plate_model_id: int, order_item_id?: ?int, status?: LegacyPlateEntitlementStatus, price_type?: ?string}  $data
     */
    public function handle(array $data): LegacyPlateEntitlement
    {
        return LegacyPlateEntitlement::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $data['athlete_id'] ?? null,
            'event_edition_id' => $data['event_edition_id'],
            'event_participant_id' => $data['event_participant_id'] ?? null,
            'legacy_plate_model_id' => $data['legacy_plate_model_id'],
            'order_item_id' => $data['order_item_id'] ?? null,
            'status' => $data['status'] ?? LegacyPlateEntitlementStatus::PendingPayment,
            'price_type' => $data['price_type'] ?? null,
        ]);
    }
}
