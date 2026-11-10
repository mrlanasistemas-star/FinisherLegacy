<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Expo push ticket id waiting for its receipt (see the
 * create_push_tickets_table migration and App\Console\Commands\CheckPushReceipts).
 * Never stores the push token or the message content.
 *
 * `push_campaign_id` is set when the message came from a push campaign, so
 * the receipt can be counted as delivered / failed for that campaign.
 *
 * @property int|null $push_campaign_id
 */
#[Fillable(['push_device_id', 'push_campaign_id', 'ticket_id', 'status', 'error', 'checked_at', 'created_at'])]
class PushTicket extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PushCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PushCampaign::class, 'push_campaign_id');
    }

    /** @return BelongsTo<PushDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class, 'push_device_id');
    }
}
