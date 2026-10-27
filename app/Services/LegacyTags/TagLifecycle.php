<?php

namespace App\Services\LegacyTags;

use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\LegacyTag;
use App\Models\LegacyTagEvent;
use App\Models\User;

/**
 * The one place a tag changes state: updates the row and appends the
 * matching legacy_tag_events entry (from/to status, from/to location,
 * order, athlete, actor). Callers hold the row lock and the transaction.
 */
class TagLifecycle
{
    /**
     * @param  array<string, mixed>  $attributes  extra columns to update with the transition
     * @param  array<string, mixed>  $properties  extra context stored on the event
     */
    public function transition(
        LegacyTag $tag,
        LegacyTagEventType $event,
        ?LegacyTagStatus $to,
        array $attributes = [],
        ?User $actor = null,
        ?string $notes = null,
        array $properties = [],
    ): LegacyTag {
        $fromStatus = $tag->status;
        $fromLocation = $tag->inventory_location_id;

        $tag->fill($attributes);

        if ($to !== null) {
            $tag->status = $to;
        }

        $tag->save();

        LegacyTagEvent::create([
            'legacy_tag_id' => $tag->id,
            'type' => $event,
            'from_status' => $fromStatus,
            'to_status' => $tag->status,
            'from_location_id' => $fromLocation,
            'to_location_id' => $tag->inventory_location_id,
            'order_id' => $tag->order_id,
            'athlete_id' => $tag->owner_athlete_id,
            'actor_user_id' => $actor?->id,
            'notes' => $notes !== null ? mb_substr($notes, 0, 500) : null,
            'properties' => $properties === [] ? null : $properties,
            'created_at' => now(),
        ]);

        return $tag;
    }

    public function note(LegacyTag $tag, string $notes, ?User $actor = null): LegacyTag
    {
        return $this->transition($tag, LegacyTagEventType::Note, null, [], $actor, $notes);
    }
}
