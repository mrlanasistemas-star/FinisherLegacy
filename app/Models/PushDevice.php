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
 */
#[Fillable([
    'uuid', 'user_id', 'platform', 'provider', 'token', 'device_name', 'last_seen_at', 'active',
])]
class PushDevice extends Model
{
    protected function casts(): array
    {
        return [
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
