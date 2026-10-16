<?php

namespace App\Events\Community;

use App\Enums\MomentVisibility;
use App\Enums\ProfileVisibility;
use App\Models\LegacyMoment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Hay publicaciones nuevas" — a tiny hint on the public `community.feed`
 * channel so open feeds can offer a refresh pill. Only posts anyone may
 * see (public post on a public profile) are announced, and the payload
 * carries no content: the client reloads through the normal, privacy-
 * checked feed.
 */
class CommunityPostCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LegacyMoment $moment) {}

    public static function shouldAnnounce(LegacyMoment $moment): bool
    {
        $moment->loadMissing('author.athleteProfile');

        return $moment->visibility === MomentVisibility::Public
            && $moment->hidden_at === null
            && $moment->author?->athleteProfile?->profile_visibility === ProfileVisibility::Public;
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('community.feed')];
    }

    public function broadcastAs(): string
    {
        return 'community.post.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->moment->uuid,
            'author_username' => $this->moment->author?->athleteProfile?->username,
            'created_at' => $this->moment->created_at?->toIso8601String(),
        ];
    }
}
