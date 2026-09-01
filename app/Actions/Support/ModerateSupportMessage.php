<?php

namespace App\Actions\Support;

use App\Enums\SupportMessageStatus;
use App\Models\AthleteSupportMessage;

/**
 * The Athlete's approve/reject on a pending supporter message (product UX
 * consolidation brief §31-§32) — bypassed entirely when the session has
 * `auto_approve` on, in which case SubmitSupportMessage already marked it
 * approved.
 */
class ModerateSupportMessage
{
    public function approve(AthleteSupportMessage $message): AthleteSupportMessage
    {
        $message->update(['status' => SupportMessageStatus::Approved, 'approved_at' => now()]);

        return $message;
    }

    public function reject(AthleteSupportMessage $message): AthleteSupportMessage
    {
        $message->update(['status' => SupportMessageStatus::Rejected]);

        return $message;
    }
}
