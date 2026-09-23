<?php

namespace App\Queries\Social;

use App\Enums\MomentReactionType;
use App\Models\LegacyMoment;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every list of Moments (feed, explore, a profile's moments) goes through
 * here: visibility rules from SocialVisibility + one eager-load set sized
 * for MomentResource, so a page of 15 cards is a fixed number of queries
 * regardless of page size.
 */
class MomentQuery
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    /**
     * @return Builder<LegacyMoment>
     */
    public function visibleTo(?User $viewer): Builder
    {
        return $this->withCardData($this->visibility->scopeVisibleMoments(LegacyMoment::query(), $viewer), $viewer);
    }

    /**
     * @param  Builder<LegacyMoment>  $query
     * @return Builder<LegacyMoment>
     */
    public function withCardData(Builder $query, ?User $viewer): Builder
    {
        return $query
            ->with([
                'author.athleteProfile',
                'media.eventMedia',
                'eventParticipant.eventEdition.event',
                'eventParticipant.eventRace',
                'eventParticipant.result',
                'medal.images',
                'gear.product',
                'viewerReactions' => fn ($q) => $q->where('user_id', $viewer->id ?? 0),
            ])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('type', MomentReactionType::Like),
                'reactions as cheers_count' => fn ($q) => $q->where('type', MomentReactionType::Cheer),
                'comments',
            ]);
    }

    /**
     * Newest first, cursor-paginated (stable under concurrent inserts —
     * a new Moment never shifts the next page).
     *
     * @param  Builder<LegacyMoment>  $query
     * @return CursorPaginator<int, LegacyMoment>
     */
    public function paginate(Builder $query, int $perPage): CursorPaginator
    {
        return $query->orderByDesc('legacy_moments.id')->cursorPaginate($perPage);
    }

    public function load(LegacyMoment $moment, ?User $viewer): LegacyMoment
    {
        /** @var LegacyMoment */
        return $this->withCardData(LegacyMoment::query()->whereKey($moment->id), $viewer)->firstOrFail();
    }
}
