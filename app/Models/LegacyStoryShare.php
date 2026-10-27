<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recipient link ("Para mamá"): opens the story without an account or an
 * app, even when the story itself is private. Revocable by the owner.
 */
#[Fillable(['legacy_story_id', 'token', 'label', 'open_count', 'last_opened_at', 'revoked_at'])]
class LegacyStoryShare extends Model
{
    protected function casts(): array
    {
        return [
            'open_count' => 'integer',
            'last_opened_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegacyStory, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(LegacyStory::class, 'legacy_story_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function url(): string
    {
        return route('legacy.share.show', $this->token);
    }
}
