<?php

namespace App\Models;

use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagActivationPlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to write the permanent URL on a blank NFC chip and confirm it
 * (docs/renovacion/manual-activacion-nfc.md). Created by
 * App\Actions\LegacyTags\StartTagActivation, closed by
 * ConfirmTagActivation / FailTagActivation. `write_url` is always built
 * server-side from config('legacy_tags.public_base_url') — never input.
 */
#[Fillable([
    'uuid', 'legacy_tag_id', 'user_id', 'platform', 'nfc_uid_hash', 'status', 'write_url', 'expires_at',
    'confirmed_at', 'failure_reason',
])]
#[Hidden(['nfc_uid_hash'])]
class LegacyTagActivationIntent extends Model
{
    protected $table = 'legacy_tag_activation_intents';

    protected function casts(): array
    {
        return [
            'platform' => LegacyTagActivationPlatform::class,
            'status' => LegacyTagActivationIntentStatus::class,
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'legacy_tag_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->status === LegacyTagActivationIntentStatus::Expired || $this->expires_at->isPast();
    }
}
