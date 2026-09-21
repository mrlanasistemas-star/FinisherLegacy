<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['athlete_support_session_id', 'display_name', 'email', 'relationship'])]
class SupportContributor extends Model
{
    /** @return BelongsTo<AthleteSupportSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AthleteSupportSession::class, 'athlete_support_session_id');
    }

    /** @return HasMany<AthleteSupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AthleteSupportMessage::class, 'support_contributor_id');
    }
}
