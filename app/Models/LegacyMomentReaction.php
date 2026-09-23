<?php

namespace App\Models;

use App\Enums\MomentReactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property MomentReactionType $type
 */
#[Fillable(['legacy_moment_id', 'user_id', 'type'])]
class LegacyMomentReaction extends Model
{
    protected function casts(): array
    {
        return ['type' => MomentReactionType::class];
    }

    /** @return BelongsTo<LegacyMoment, $this> */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(LegacyMoment::class, 'legacy_moment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
