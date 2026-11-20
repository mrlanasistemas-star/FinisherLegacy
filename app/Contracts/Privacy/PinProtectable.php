<?php

namespace App\Contracts\Privacy;

use App\Models\User;

/**
 * A piece of the owner's private content that can be locked behind the
 * optional content PIN (App\Services\Privacy\ContentPin): a memory
 * (LegacyStory), a moment (LegacyMoment) or an event photo/video
 * (AthleteEventMedia). Implemented through App\Models\Concerns\PinProtection.
 */
interface PinProtectable
{
    /** The owner flagged it AND it has to stay hidden until unlocked. */
    public function isPinProtected(): bool;

    /** Only the owner can ever unlock it — nobody else, not even staff. */
    public function isPinOwnedBy(User $user): bool;

    /** True while protected and the current request holds no valid unlock grant of its owner. */
    public function isPinLocked(): bool;
}
