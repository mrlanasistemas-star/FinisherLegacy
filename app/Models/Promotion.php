<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * An "oferta": an automatic discount (no code) on the unit price of every
 * product or of a selected set, inside an optional date window. Different
 * from a Coupon, which the customer types and which discounts the cart.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $badge_label
 * @property CouponType $type
 * @property int $value
 * @property string $applies_to
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
#[Fillable([
    'uuid', 'name', 'badge_label', 'description', 'type', 'value', 'applies_to',
    'starts_at', 'ends_at', 'active', 'created_by',
])]
class Promotion extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function discountFor(int $amountMinor): int
    {
        $discount = $this->type === CouponType::Percentage
            ? intdiv($amountMinor * $this->value, 100)
            : $this->value;

        return max(0, min($discount, $amountMinor));
    }

    public function label(): string
    {
        return $this->badge_label ?: ($this->type === CouponType::Percentage ? "-{$this->value}%" : 'Oferta');
    }
}
