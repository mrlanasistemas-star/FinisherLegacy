<?php

namespace App\Events\Community;

use App\Models\LegacyMomentComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A new "mensaje de apoyo" on your post — sent only to the post author's
 * private user channel (the same one Laravel notifications use), never
 * to a shared channel.
 */
class CommunityCommentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LegacyMomentComment $comment, public int $recipientId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'community.comment.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->comment->loadMissing(['moment', 'author.athleteProfile']);

        return [
            'moment_uuid' => $this->comment->moment?->uuid,
            'comment_uuid' => $this->comment->uuid,
            'author' => [
                'name' => $this->comment->author?->name,
                'username' => $this->comment->author?->athleteProfile?->username,
            ],
            'excerpt' => Str::limit($this->comment->body, 120),
            'created_at' => $this->comment->created_at?->toIso8601String(),
        ];
    }
}
