<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One verified App Store / Google Play transaction of a photo expansion
 * (docs/renovacion/compras-dentro-de-app.md). Unique per
 * (platform, transaction_key): the idempotency key that makes a replayed
 * or retried transaction return the existing grant instead of a second one.
 *
 * @property string $platform ios|android
 * @property string $transaction_key
 * @property string|null $transaction_id
 * @property string $product_id
 * @property int $user_id
 * @property int|null $legacy_story_id
 * @property int|null $order_id
 * @property string $status granted|revoked
 * @property Carbon|null $revoked_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'uuid', 'platform', 'transaction_key', 'transaction_id', 'original_transaction_id', 'purchase_token',
    'product_id', 'user_id', 'legacy_story_id', 'photo_expansion_pack_id', 'order_id', 'status',
    'environment', 'quantity', 'amount_minor', 'currency', 'amount_source',
    'purchased_at', 'acknowledged_at', 'revoked_at', 'revocation_reason', 'metadata',
])]
class StorePurchase extends Model
{
    public const string STATUS_GRANTED = 'granted';

    public const string STATUS_REVOKED = 'revoked';

    protected $hidden = ['purchase_token'];

    protected static function booted(): void
    {
        static::creating(function (StorePurchase $purchase) {
            $purchase->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount_minor' => 'integer',
            'purchased_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
            'purchase_token' => 'encrypted',
        ];
    }

    /** Android purchase tokens are long; the key is their SHA-256. */
    public static function androidKey(string $purchaseToken): string
    {
        return hash('sha256', $purchaseToken);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LegacyStory, $this> */
    public function legacyStory(): BelongsTo
    {
        return $this->belongsTo(LegacyStory::class);
    }

    /** @return BelongsTo<PhotoExpansionPack, $this> */
    public function photoExpansionPack(): BelongsTo
    {
        return $this->belongsTo(PhotoExpansionPack::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
