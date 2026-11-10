<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One-time ticket that lets the mobile app open the OpenPay web checkout
 * of ONE Order in an in-app browser (docs/renovacion/pagos.md › App).
 *
 * Only the SHA-256 of the token is stored. It is consumed atomically by
 * App\Actions\Commerce\ConsumeCheckoutHandoffToken (single use) and is
 * never valid after `expires_at`. Consuming it grants a session scoped to
 * that Order's checkout routes only — it is not a web login.
 *
 * @property int $id
 * @property string $token_hash
 * @property int $user_id
 * @property int $order_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $created_ip
 * @property string|null $used_ip
 */
#[Fillable(['token_hash', 'user_id', 'order_id', 'expires_at', 'used_at', 'created_ip', 'used_ip'])]
class CheckoutHandoffToken extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
