<?php

namespace App\Policies;

use App\Models\LegacyMomentComment;
use App\Models\User;

class LegacyMomentCommentPolicy
{
    /**
     * The comment's author, or the author of the Moment it was left on
     * (they own that space).
     */
    public function delete(User $user, LegacyMomentComment $comment): bool
    {
        return $user->id === $comment->user_id || $user->id === $comment->moment?->user_id;
    }
}
