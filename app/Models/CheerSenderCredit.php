<?php

namespace App\Models;

use App\Enums\CheerChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messages left from a pack a sender bought on a cheer link or card
 * (docs/renovacion/tarjeta-animo.md §Paquetes). Senders have no account, so
 * the credit belongs to the HMAC of a random cookie their browser keeps
 * (`cheer_sender`) — clearing cookies loses the remaining credits, which the
 * public page says before paying. Created once per paid OrderItem
 * (`order_item_id` is unique → idempotent on webhook replays).
 *
 * @property int $id
 * @property int $cheer_link_id
 * @property int|null $legacy_tag_id
 * @property CheerChannel $channel
 * @property string $sender_key_hash
 * @property int $credits_total
 * @property int $credits_remaining
 * @property int|null $order_item_id
 */
#[Fillable(['cheer_link_id', 'legacy_tag_id', 'channel', 'sender_key_hash', 'credits_total', 'credits_remaining', 'order_item_id'])]
class CheerSenderCredit extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => CheerChannel::class,
            'credits_total' => 'integer',
            'credits_remaining' => 'integer',
        ];
    }

    /** @return BelongsTo<CheerLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(CheerLink::class, 'cheer_link_id');
    }
}
