<?php

namespace App\Models;

use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privacy-first tag analytics: which event, through which channel (NFC, QR,
 * typed code, link), whether the owner opened it. No IP, no user agent —
 * only a daily-rotating keyed hash to count unique visitors.
 */
#[Fillable(['legacy_tag_id', 'event', 'source', 'user_id', 'visitor_hash', 'is_owner', 'created_at'])]
class LegacyTagScan extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => TagScanEvent::class,
            'source' => TagAccessSource::class,
            'is_owner' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'legacy_tag_id');
    }

    /** @return Builder<LegacyTagScan> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('legacy.scans.retention_days', 365)));
    }
}
