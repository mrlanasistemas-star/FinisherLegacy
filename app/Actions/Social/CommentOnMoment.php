<?php

namespace App\Actions\Social;

use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\User;

class CommentOnMoment
{
    public function __construct(private readonly NotifySocialActivity $notify) {}

    public function handle(User $author, LegacyMoment $moment, string $body): LegacyMomentComment
    {
        $comment = LegacyMomentComment::create([
            'legacy_moment_id' => $moment->id,
            'user_id' => $author->id,
            'body' => trim($body),
        ]);

        if ($author->id !== $moment->user_id) {
            $this->notify->momentComment($moment, $author, $comment->body);
        }

        return $comment;
    }
}
