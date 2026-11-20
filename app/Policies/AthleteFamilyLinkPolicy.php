<?php

namespace App\Policies;

use App\Models\AthleteFamilyLink;
use App\Models\User;

/**
 * Family links: the athlete who invited owns the row (label, remove); the
 * invited person may only answer their own invitation or leave. Nobody
 * else can see or touch a link — controllers answer 404 for them.
 */
class AthleteFamilyLinkPolicy
{
    /** Rename the label — owner only. */
    public function update(User $user, AthleteFamilyLink $link): bool
    {
        return $link->athlete_user_id === $user->id;
    }

    /** Owner removes the person, or the contact leaves on their own. */
    public function delete(User $user, AthleteFamilyLink $link): bool
    {
        return $link->athlete_user_id === $user->id
            || ($link->contact_user_id !== null && $link->contact_user_id === $user->id);
    }

    /** Accept / decline an email invite addressed to this account. */
    public function respond(User $user, AthleteFamilyLink $link): bool
    {
        return $link->contact_user_id !== null && $link->contact_user_id === $user->id;
    }
}
