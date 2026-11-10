<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\ProductPriceType;
use App\Enums\ProductType;
use App\Enums\StoryQuotaKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A sellable block of extra capacity for a Legacy story: `photos` units of
 * `kind` (photos, videos or voice messages — the column keeps its first
 * name). Sold `one_time` (permanent) or as a prepaid term
 * (App\Enums\BillingPeriod). Its prices are ProductPriceSchedule rows
 * (photo_expansion_pack_id) — one immutable row per priced window, with
 * optional price steps, so a past purchase always keeps the price it paid.
 *
 * @property int $photos Units of `kind` the pack adds.
 * @property StoryQuotaKind $kind
 * @property BillingPeriod $billing_period
 * @property bool $active
 * @property ProductType|null $product_type
 * @property Carbon|null $archived_at
 * @property string|null $apple_product_id Consumable product id in App Store Connect (in-app purchase; the store owns its price).
 * @property string|null $google_product_id One-time consumable product id in Play Console.
 */
#[Fillable(['uuid', 'kind', 'billing_period', 'name', 'description', 'photos', 'product_id', 'product_type', 'apple_product_id', 'google_product_id', 'active', 'archived_at', 'sort_order', 'created_by'])]
class PhotoExpansionPack extends Model
{
    /** Internal, never-listed product every expansion OrderItem points at. */
    public const string PRODUCT_SLUG = 'ampliacion-fotos-recuerdo';

    /** Entitlement source of every purchased expansion, whatever its kind. */
    public const string ENTITLEMENT_SOURCE = 'photo_expansion';

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => 'photos', 'billing_period' => 'one_time'];

    protected static function booted(): void
    {
        static::creating(function (PhotoExpansionPack $pack) {
            $pack->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'photos' => 'integer',
            'kind' => StoryQuotaKind::class,
            'billing_period' => BillingPeriod::class,
            'active' => 'boolean',
            'archived_at' => 'datetime',
            'product_type' => ProductType::class,
        ];
    }

    /**
     * @param  Builder<PhotoExpansionPack>  $query
     * @return Builder<PhotoExpansionPack>
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('active', true)->whereNull('archived_at');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductPriceSchedule, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPriceSchedule::class);
    }

    /** @return HasMany<MediaEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(MediaEntitlement::class);
    }

    /** The schedule row in force at $at (latest start wins), if any. */
    public function priceAt(?\DateTimeInterface $at = null): ?ProductPriceSchedule
    {
        $at ??= now();

        return $this->prices()
            ->where('price_type', ProductPriceType::Standard->value)
            ->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->orderByRaw('starts_at is null')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Units of its kind the pack adds (alias of the `photos` column). */
    public function units(): int
    {
        return (int) $this->photos;
    }

    /** Order item / receipt line: "Pack · +20 fotos · 12 meses". */
    public function lineName(): string
    {
        $months = $this->billing_period->months();

        return "{$this->name} · +{$this->units()} {$this->kind->noun($this->units())}".($months !== null ? " · {$months} meses" : '');
    }

    /** The store product id for an in-app purchase platform (`ios` / `android`). */
    public function storeProductId(string $platform): ?string
    {
        $id = $platform === 'ios' ? $this->apple_product_id : ($platform === 'android' ? $this->google_product_id : null);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
