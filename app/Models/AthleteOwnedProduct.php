<?php

namespace App\Models;

use App\Enums\AthleteOwnedProductStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A concrete physical unit of a QR-capable product owned by an Athlete
 * (brief §84-§93/§103). `asset_code` is the unguessable, no-PII identifier
 * a public `/api/v1/gear/{code}` lookup resolves — see
 * App\Services\Commerce\AssetCodeService.
 */
#[Fillable([
    'uuid', 'athlete_id', 'order_item_id', 'product_id', 'product_variant_id',
    'serial_code', 'asset_code', 'status', 'acquired_at', 'activated_at', 'metadata',
])]
class AthleteOwnedProduct extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => AthleteOwnedProductStatus::class,
            'acquired_at' => 'datetime',
            'activated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
