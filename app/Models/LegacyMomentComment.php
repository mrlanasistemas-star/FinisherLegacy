<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $legacy_moment_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $hidden_at Hidden by moderation (author still sees it)
 * @property int|null $hidden_by
 * @property Carbon $created_at
 */
#[Fillable(['uuid', 'legacy_moment_id', 'user_id', 'body', 'hidden_at', 'hidden_by'])]
class LegacyMomentComment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (LegacyMomentComment $comment) {
            $comment->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<LegacyMoment, $this> */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(LegacyMoment::class, 'legacy_moment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
