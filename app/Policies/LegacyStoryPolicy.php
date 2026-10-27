<?php

namespace App\Policies;

use App\Enums\StoryVisibility;
use App\Models\LegacyStory;
use App\Models\User;

/**
 * Who can open a Legacy story (docs/architecture/commerce-os.md
 * §Activación y Mi Legacy):
 *
 *   owner     → always (view, edit, media, shares, print)
 *   public    → anyone (the only indexable kind)
 *   unlisted  → anyone who holds the tag or the direct link — the
 *               10-char random public id IS the capability; never listed,
 *               never in a sitemap, always `noindex`
 *   private   → owner only (recipient share links bypass this policy on
 *               purpose — the token is the owner's explicit grant)
 *
 * Staff never read private memories through this policy.
 */
class LegacyStoryPolicy
{
    public function view(?User $user, LegacyStory $story): bool
    {
        if ($story->isOwnedBy($user)) {
            return true;
        }

        return $story->visibility !== StoryVisibility::Private;
    }

    public function update(User $user, LegacyStory $story): bool
    {
        return $story->isOwnedBy($user);
    }

    public function delete(User $user, LegacyStory $story): bool
    {
        return $story->isOwnedBy($user);
    }

    /** Recipient links ("Para mamá"). */
    public function share(User $user, LegacyStory $story): bool
    {
        return $story->isOwnedBy($user);
    }

    /** Print template — renders the owner's private media files. */
    public function print(User $user, LegacyStory $story): bool
    {
        return $story->isOwnedBy($user);
    }
}
