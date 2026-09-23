<?php

namespace App\Policies;

use App\Models\LegacyMoment;
use App\Models\User;
use App\Services\Social\SocialVisibility;

class LegacyMomentPolicy
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    public function view(?User $user, LegacyMoment $moment): bool
    {
        return $this->visibility->canViewMoment($moment, $user);
    }

    /**
     * Reacting/commenting requires seeing it — and never on your own
     * blocked-relationship boundary (canViewMoment already covers blocks).
     */
    public function interact(User $user, LegacyMoment $moment): bool
    {
        return $this->visibility->canViewMoment($moment, $user);
    }

    public function update(User $user, LegacyMoment $moment): bool
    {
        return $user->id === $moment->user_id;
    }

    public function delete(User $user, LegacyMoment $moment): bool
    {
        return $user->id === $moment->user_id;
    }
}
