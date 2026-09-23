<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'email', 'email_is_private_relay', 'last_used_at'])]
class UserSocialAccount extends Model
{
    protected function casts(): array
    {
        return [
            'email_is_private_relay' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
