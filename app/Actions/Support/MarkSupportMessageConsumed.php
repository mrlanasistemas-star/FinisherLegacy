<?php

namespace App\Actions\Support;

use App\Enums\SupportMessageStatus;
use App\Models\AthleteSupportMessage;

/**
 * Idempotent — a message already marked consumed stays exactly as it was
 * (product UX consolidation brief §38: GPS fluctuation must never
 * double-play).
 */
class MarkSupportMessageConsumed
{
    public function handle(AthleteSupportMessage $message): AthleteSupportMessage
    {
        if ($message->status === SupportMessageStatus::Consumed) {
            return $message;
        }

        $message->update(['status' => SupportMessageStatus::Consumed, 'consumed_at' => now()]);

        return $message;
    }
}
