<?php

namespace App\Models;

use App\Enums\AthleteIdentityStatus;
use Database\Factories\AthleteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The canonical sporting identity — one real person, independent of
 * whether they ever created a Finisher Legacy account. See
 * docs/adr/0004-athlete-canonical-identity.md.
 *
 * Never hard-deleted: a retired duplicate gets `identity_status = merged`
 * + `merged_into_athlete_id` set (App\Actions\Athletes\MergeAthletes) so
 * every historical Plate/Medal/EventParticipant reference stays valid.
 */
#[Fillable([
    'uuid', 'user_id', 'first_name', 'last_name', 'full_name',
    'normalized_first_name', 'normalized_last_name', 'normalized_full_name',
    'email', 'normalized_email', 'phone', 'normalized_phone',
    'birth_date', 'gender', 'country', 'identity_status', 'merged_into_athlete_id',
])]
class Athlete extends Model
{
    /** @use HasFactory<AthleteFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'identity_status' => AthleteIdentityStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Athlete, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'merged_into_athlete_id');
    }

    /** @return HasMany<Athlete, $this> */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(Athlete::class, 'merged_into_athlete_id');
    }

    /** @return HasMany<EventParticipant, $this> */
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /** @return HasMany<Plate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }

    /** @return HasMany<Medal, $this> */
    public function medals(): HasMany
    {
        return $this->hasMany(Medal::class);
    }

    /** @return HasMany<AthleteExternalIdentity, $this> */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(AthleteExternalIdentity::class);
    }

    /** @return HasMany<LegacyPlateEntitlement, $this> */
    public function legacyPlateEntitlements(): HasMany
    {
        return $this->hasMany(LegacyPlateEntitlement::class);
    }

    /** @return HasMany<AthleteOwnedProduct, $this> */
    public function ownedProducts(): HasMany
    {
        return $this->hasMany(AthleteOwnedProduct::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<AthleteEventMedia, $this> */
    public function eventMedia(): HasMany
    {
        return $this->hasMany(AthleteEventMedia::class);
    }

    public function isMerged(): bool
    {
        return $this->identity_status === AthleteIdentityStatus::Merged;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
