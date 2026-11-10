<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user of a push campaign's audience, frozen when the campaign starts
 * sending (docs/renovacion/campanas-push.md). `status`: pending → sent |
 * failed | skipped (opted out or deactivated between snapshot and batch).
 * `opened_at` is set when the app reports the push was tapped
 * (POST /api/v1/notifications/{id}/opened).
 */
#[Fillable([
    'push_campaign_id', 'user_id', 'batch', 'devices_count', 'status', 'error', 'sent_at', 'opened_at',
])]
class PushCampaignRecipient extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_SKIPPED = 'skipped';

    protected function casts(): array
    {
        return [
            'batch' => 'integer',
            'devices_count' => 'integer',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PushCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PushCampaign::class, 'push_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
