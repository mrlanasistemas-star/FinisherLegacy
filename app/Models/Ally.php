<?php

namespace App\Models;

use App\Enums\AllyStatus;
use App\Enums\AllyType;
use Database\Factories\AllyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A partner business that resells Finisher Legacy inventory (gym, store,
 * organizer…). Not a user: its staff accounts link through ally_user, and
 * its consigned stock lives at inventory locations of type `ally`.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'type', 'status', 'contact_name', 'email', 'phone', 'city', 'state', 'address',
    'commission_percent', 'commercial_terms', 'notes',
])]
class Ally extends Model
{
    /** @use HasFactory<AllyFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => AllyType::class,
            'status' => AllyStatus::class,
            'commission_percent' => 'decimal:2',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<InventoryLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<LegacyTag, $this> */
    public function soldTags(): HasMany
    {
        return $this->hasMany(LegacyTag::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status', 'commission_percent', 'commercial_terms'])->logOnlyDirty();
    }
}
