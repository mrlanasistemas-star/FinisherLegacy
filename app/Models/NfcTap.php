<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Short-lived tap log — hashed IP, truncated UA, optional user. Pruned
 * after `nfc.tap_retention_days` (scheduled `model:prune`). Never PII.
 */
#[Fillable(['nfc_tag_id', 'user_id', 'ip_hash', 'user_agent', 'channel', 'created_at'])]
#[WithoutTimestamps]
class NfcTap extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<NfcTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(NfcTag::class, 'nfc_tag_id');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('nfc.tap_retention_days', 90)));
    }
}
