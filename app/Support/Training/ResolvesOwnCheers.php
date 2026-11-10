<?php

namespace App\Support\Training;

use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\User;

/**
 * Owner-only lookups for Entrenamientos controllers (web + API). Every
 * lookup is scoped to the athlete, so someone else's uuid is a plain 404 —
 * never a 403 that would confirm the uuid exists.
 */
trait ResolvesOwnCheers
{
    protected function ownMessage(User $user, string $uuid): CheerMessage
    {
        return CheerMessage::query()
            ->visibleFor($user)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    protected function ownLink(User $user, string $uuid): CheerLink
    {
        return CheerLink::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
