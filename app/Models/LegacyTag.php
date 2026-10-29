<?php

namespace App\Models;

use App\Enums\LegacyTagStatus;
use App\Enums\SalesChannel;
use App\Support\LegacyTags\TagCode;
use Database\Factories\LegacyTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One physical Finisher Legacy Tag (docs/architecture/commerce-os.md §Tag).
 *
 * Traceable end to end: lot → location → order/channel → delivery →
 * activation → athlete → story. `public_code` is what the NFC chip carries
 * (/t/{code}) and what a person can type at /activar; one tag, one story.
 * Status changes only through App\Actions\LegacyTags\* (each writes a
 * legacy_tag_events row) — never by assigning `status` in a controller.
 */
#[Fillable([
    'uuid', 'serial', 'public_code', 'product_variant_id', 'inventory_lot_id', 'inventory_location_id', 'nfc_tag_id',
    'nfc_uid_hash', 'status', 'sales_channel', 'ally_id', 'order_id', 'order_item_id', 'owner_athlete_id',
    'athlete_owned_product_id', 'replaces_tag_id', 'received_at', 'sold_at', 'delivered_at', 'activated_at',
    'first_opened_at', 'last_opened_at', 'open_count', 'disabled_reason', 'notes', 'metadata',
])]
#[Hidden(['nfc_uid_hash'])]
class LegacyTag extends Model
{
    /** @use HasFactory<LegacyTagFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => LegacyTagStatus::class,
            'sales_channel' => SalesChannel::class,
            'received_at' => 'datetime',
            'sold_at' => 'datetime',
            'delivered_at' => 'datetime',
            'activated_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'open_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'inventory_lot_id');
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** @return BelongsTo<NfcTag, $this> */
    public function nfcTag(): BelongsTo
    {
        return $this->belongsTo(NfcTag::class);
    }

    /** @return BelongsTo<Ally, $this> */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Athlete, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'owner_athlete_id');
    }

    /** @return BelongsTo<AthleteOwnedProduct, $this> */
    public function ownedProduct(): BelongsTo
    {
        return $this->belongsTo(AthleteOwnedProduct::class, 'athlete_owned_product_id');
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_tag_id');
    }

    /** @return HasOne<LegacyTag, $this> */
    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_tag_id');
    }

    /** @return HasOne<LegacyStory, $this> */
    public function story(): HasOne
    {
        return $this->hasOne(LegacyStory::class);
    }

    /** @return HasMany<LegacyTagEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LegacyTagEvent::class)->orderByDesc('id');
    }

    /** @return HasMany<LegacyTagScan, $this> */
    public function scans(): HasMany
    {
        return $this->hasMany(LegacyTagScan::class);
    }

    /** @return HasMany<SupportCase, $this> */
    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    /** Short, permanent URL written to the NFC chip. */
    public function nfcUrl(): string
    {
        return route('tag.open', $this->public_code);
    }

    /** Human form printed on the tag/packaging: XXXX-XXXX-XXXX. */
    public function displayCode(): string
    {
        return TagCode::format($this->public_code);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'inventory_location_id', 'owner_athlete_id', 'order_id', 'disabled_reason'])
            ->logOnlyDirty();
    }
}
