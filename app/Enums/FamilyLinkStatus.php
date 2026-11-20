<?php

namespace App\Enums;

/**
 * Lifecycle of an athlete ↔ family contact link (athlete_family_links).
 * Only `Accepted` links can receive a live-tracking share; every other
 * state grants nothing.
 */
enum FamilyLinkStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';

    /** Still "open" — blocks a duplicate invite between the same two people. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Accepted;
    }
}
