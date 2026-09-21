<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered push token for a future mobile app (product consolidation
 * brief §32) — this repo only stores it; sending an actual push
 * notification through Expo/FCM/APNs is decided and implemented in the
 * Mobile repo, never here.
 *
 * `token` is encrypted at rest (it identifies a physical device and can
 * be replayed against a push provider) but is still recoverable — a
 * future push-sending job needs the real value, so this is `encrypted`,
 * never a one-way hash. `token_hash` is the plain SHA-256 digest of that
 * same plaintext, kept only so the (user, token) uniqueness check and the
 * "same token re-registers the same device" lookup have something
 * queryable — two encryptions of the same plaintext never produce the
 * same ciphertext, so `token` itself cannot serve that role.
 */
#[Fillable([
    'uuid', 'user_id', 'platform', 'provider', 'token', 'device_name', 'last_seen_at', 'active',
])]
class PushDevice extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $device) {
            if ($device->isDirty('token')) {
                $device->token_hash = hash('sha256', $device->token);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
