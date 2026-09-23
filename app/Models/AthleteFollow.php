<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $follower_id
 * @property int $following_id
 */
#[Fillable(['follower_id', 'following_id'])]
class AthleteFollow extends Model
{
    /** @return BelongsTo<User, $this> */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /** @return BelongsTo<User, $this> */
    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
