<?php

namespace App\Actions\Training;

use App\Enums\CheerMessageStatus;
use App\Models\CheerBlock;
use App\Models\CheerMessage;
use App\Models\User;

/**
 * The athlete's actions on their own cheer messages (Entrenamientos):
 * listened, favorite, hide and block the sender. Callers resolve the
 * message scoped to the athlete first (404 for anyone else) — see
 * App\Support\Training\ResolvesOwnCheers. Web and API call the same methods.
 */
class ManageCheerMessages
{
    public function markListened(CheerMessage $message): void
    {
        if ($message->listened_at === null) {
            $message->forceFill(['listened_at' => now()])->save();
        }
    }

    public function setFavorite(CheerMessage $message, bool $favorite): void
    {
        $message->forceFill(['favorite' => $favorite])->save();
    }

    public function hide(CheerMessage $message): void
    {
        $message->forceFill(['status' => CheerMessageStatus::Hidden])->save();
    }

    /**
     * Blocks the sender (their IP hash) on that link and hides everything
     * they already sent through it. Without a hash (very old/odd request)
     * only this message is hidden.
     */
    public function blockSender(CheerMessage $message, User $user): void
    {
        if ($message->sender_ip_hash === null) {
            $this->hide($message);

            return;
        }

        CheerBlock::query()->firstOrCreate(
            ['cheer_link_id' => $message->cheer_link_id, 'sender_ip_hash' => $message->sender_ip_hash],
            ['user_id' => $user->id, 'cheer_message_id' => $message->id],
        );

        CheerMessage::query()
            ->where('cheer_link_id', $message->cheer_link_id)
            ->where('sender_ip_hash', $message->sender_ip_hash)
            ->update(['status' => CheerMessageStatus::Hidden->value, 'updated_at' => now()]);

        activity()->causedBy($user)->performedOn($message->link)->log('training.cheer_sender_blocked');
    }
}
