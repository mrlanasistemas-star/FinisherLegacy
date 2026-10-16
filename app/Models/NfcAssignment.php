<?php

namespace App\Models;

use App\Enums\NfcTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What an NFC tag opens and who owns it (docs/NFC.md §Modelo). One open row
 * per tag (`ended_at` null); a transfer or unassign closes it and opens a
 * new one, so ownership history is never overwritten.
 *
 * Target FKs per type (explicit, constrained — never a free morph):
 * - legacy_plate → plate_id
 * - product      → product_id (+ product_variant_id), athlete_owned_product_id
 *                  once a real unit exists (from an order, or created on claim)
 * - profile      → no FK: opens the owner's public profile
 * - event        → event_id (ownerless, active from assignment)
 *
 * `athlete_id` is the owner; `activated_at` set = claimed/connected.
 */
#[Fillable([
    'nfc_tag_id', 'target_type', 'plate_id', 'product_id', 'product_variant_id',
    'athlete_owned_product_id', 'event_id', 'athlete_id', 'assigned_by_user_id',
    'assigned_at', 'activated_at', 'ended_at', 'end_reason',
    'transfer_to_athlete_id', 'transfer_token_hash', 'transfer_expires_at',
])]
#[Hidden(['transfer_token_hash'])]
class NfcAssignment extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'target_type' => NfcTargetType::class,
            'assigned_at' => 'datetime',
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
            'transfer_expires_at' => 'datetime',
        ];
    }

    /** @param  Builder<NfcAssignment>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isClaimed(): bool
    {
        return $this->activated_at !== null;
    }

    /** @return BelongsTo<NfcTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(NfcTag::class, 'nfc_tag_id');
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<Plate, $this> */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<AthleteOwnedProduct, $this> */
    public function ownedProduct(): BelongsTo
    {
        return $this->belongsTo(AthleteOwnedProduct::class, 'athlete_owned_product_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /** @return BelongsTo<Athlete, $this> */
    public function transferTo(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'transfer_to_athlete_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['target_type', 'plate_id', 'product_id', 'athlete_owned_product_id', 'event_id', 'athlete_id', 'activated_at', 'ended_at', 'end_reason', 'transfer_to_athlete_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
