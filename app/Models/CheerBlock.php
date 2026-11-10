<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sender the athlete blocked on one cheer link (Entrenamientos). Senders
 * have no account, so the block is the HMAC of their IP for that link;
 * new messages from it are dropped without telling the sender.
 *
 * @property int $id
 * @property int $user_id
 * @property int $cheer_link_id
 * @property string $sender_ip_hash
 * @property int|null $cheer_message_id
 */
#[Fillable(['user_id', 'cheer_link_id', 'sender_ip_hash', 'cheer_message_id'])]
class CheerBlock extends Model
{
    /** @return BelongsTo<CheerLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(CheerLink::class, 'cheer_link_id');
    }
}
