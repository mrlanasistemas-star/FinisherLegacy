<?php

namespace App\Queries\Social;

use App\Enums\MomentVisibility;
use App\Models\LegacyMoment;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Support\Facades\Storage;

/**
 * The horizontal avatar strip on top of the feed (story-style): people
 * you follow who posted lately first (gold ring), then suggestions.
 * Two queries + SuggestedAthletes; no per-avatar query.
 */
class PeopleStrip
{
    private const int RECENT_DAYS = 14;

    public function __construct(
        private readonly SocialVisibility $visibility,
        private readonly SuggestedAthletes $suggested,
    ) {}

    /**
     * @return list<array{username: string|null, name: string, photo_url: string|null, recent: bool, following: bool}>
     */
    public function handle(?User $viewer, int $limit = 14): array
    {
        $people = [];
        $following = $this->visibility->followingIds($viewer);

        if ($viewer !== null && $following !== []) {
            $people = $this->visibility->scopeListableUsers(User::query(), $viewer)
                ->whereIn('users.id', $following)
                ->select('users.*')
                ->addSelect(['latest_moment_at' => LegacyMoment::query()
                    ->selectRaw('MAX(legacy_moments.created_at)')
                    ->whereColumn('legacy_moments.user_id', 'users.id')
                    ->whereNull('legacy_moments.hidden_at')
                    ->where('legacy_moments.visibility', '!=', MomentVisibility::Private->value)
                    ->where('legacy_moments.created_at', '>=', now()->subDays(self::RECENT_DAYS))])
                ->with('athleteProfile')
                ->orderByRaw('CASE WHEN latest_moment_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('latest_moment_at')
                ->orderByDesc('users.id')
                ->limit($limit)
                ->get()
                ->map(fn (User $user) => $this->item($user, $user->getAttribute('latest_moment_at') !== null, true))
                ->all();
        }

        $room = $limit - count($people);

        if ($room > 0) {
            $people = [...$people, ...$this->suggested->handle($viewer, $room)
                ->map(fn (User $user) => $this->item($user, false, false))
                ->all()];
        }

        return array_values($people);
    }

    /**
     * @return array{username: string|null, name: string, photo_url: string|null, recent: bool, following: bool}
     */
    private function item(User $user, bool $recent, bool $following): array
    {
        $profile = $user->athleteProfile;

        return [
            'username' => $profile?->username,
            'name' => $user->name,
            'photo_url' => $profile?->profile_photo_path ? Storage::disk('public')->url($profile->profile_photo_path) : null,
            'recent' => $recent,
            'following' => $following,
        ];
    }
}
