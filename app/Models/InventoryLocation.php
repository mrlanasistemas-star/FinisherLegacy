<?php

namespace App\Models;

use App\Enums\InventoryLocationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where stock physically is: warehouse, an ally's shop, an event, a stand
 * or in transit. Aggregate quantities live in inventory_levels; individual
 * tags point here through legacy_tags.inventory_location_id.
 */
#[Fillable(['name', 'slug', 'type', 'ally_id', 'event_edition_id', 'address', 'notes', 'active'])]
class InventoryLocation extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'type' => InventoryLocationType::class,
        ];
    }

    /** @return HasMany<InventoryLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    /** @return HasMany<LegacyTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(LegacyTag::class);
    }

    /** @return BelongsTo<Ally, $this> */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** Point-of-sale locations where an unreported sale can be inferred from an activation. */
    public function isFieldLocation(): bool
    {
        return in_array($this->type, [InventoryLocationType::Ally, InventoryLocationType::Event, InventoryLocationType::Stand], true);
    }
}
