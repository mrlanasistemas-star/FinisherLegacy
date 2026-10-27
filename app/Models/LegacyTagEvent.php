<?php

namespace App\Models;

use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of one physical tag (never updated, never deleted by the app). */
#[Fillable([
    'legacy_tag_id', 'type', 'from_status', 'to_status', 'from_location_id', 'to_location_id', 'order_id',
    'athlete_id', 'actor_user_id', 'notes', 'properties', 'created_at',
])]
class LegacyTagEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => LegacyTagEventType::class,
            'from_status' => LegacyTagStatus::class,
            'to_status' => LegacyTagStatus::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'legacy_tag_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
