<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AthleteOwnedProduct;
use App\Models\EventGearSelection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "Mi equipo" / Digital Closet (brief §91/§111/§115) — visible only to the
 * owning Athlete (or admin), so this is allowed to be fuller than
 * PublicGearResource.
 *
 * @mixin AthleteOwnedProduct
 */
class AthleteOwnedProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product_name' => $this->product->name,
            'variant_name' => $this->productVariant?->name,
            'status' => $this->status->value,
            'asset_code' => $this->asset_code,
            'acquired_at' => $this->acquired_at->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'usage_history' => $this->whenLoaded('gearSelections', fn () => $this->gearSelections->map(fn (EventGearSelection $selection) => [
                'event_participant_id' => $selection->event_participant_id,
                'event' => $selection->eventParticipant->eventEdition?->event?->name,
                'edition' => $selection->eventParticipant->eventEdition?->name,
                'selected_at' => $selection->selected_at->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
