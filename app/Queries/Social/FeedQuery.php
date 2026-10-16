<?php

namespace App\Queries\Social;

use App\Models\EventParticipant;
use App\Models\LegacyMoment;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Comunidad feed (spec §55) — no ML, a transparent ordering:
 *
 *  - following: you + people you follow, newest first.
 *  - discover:  every visible post, newest first (kept for the mobile API).
 *  - for_you:   every visible post, grouped by day (newest day first) and,
 *               inside a day, people you follow / you first (+2), then posts
 *               about events you took part in (+1), then the rest.
 *  - events:    like for_you, but only posts tied to an event.
 *
 * Ranked scopes compute `feed_day` / `feed_rank` in a derived table so the
 * standard cursor paginator can key on them (stable `meta.next_cursor`).
 */
class FeedQuery
{
    public const string FOR_YOU = 'for_you';

    public const string FOLLOWING = 'following';

    public const string EVENTS = 'events';

    public const string DISCOVER = 'discover';

    public const array SCOPES = [self::FOR_YOU, self::FOLLOWING, self::EVENTS, self::DISCOVER];

    public function __construct(
        private readonly MomentQuery $moments,
        private readonly SocialVisibility $visibility,
    ) {}

    /**
     * @return CursorPaginator<int, LegacyMoment>
     */
    public function paginate(?User $viewer, string $scope, ?int $perPage = null): CursorPaginator
    {
        $perPage ??= (int) config('finisher.social.feed_per_page');

        if ($scope === self::FOLLOWING && $viewer !== null) {
            $query = $this->moments->visibleTo($viewer)
                ->whereIn('legacy_moments.user_id', [...$this->visibility->followingIds($viewer), $viewer->id]);

            return $this->moments->paginate($query, $perPage);
        }

        if ($scope === self::DISCOVER) {
            return $this->moments->paginate($this->moments->visibleTo($viewer), $perPage);
        }

        return $this->ranked($viewer, $scope === self::EVENTS)
            ->orderByDesc('feed_day')
            ->orderByDesc('feed_rank')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * @return Builder<LegacyMoment>
     */
    private function ranked(?User $viewer, bool $eventsOnly): Builder
    {
        $people = $viewer === null ? [] : [...$this->visibility->followingIds($viewer), $viewer->id];
        $editions = $this->viewerEditionIds($viewer);

        [$peopleSql, $peopleBindings] = $this->inClause('legacy_moments.user_id', $people);
        [$editionSql, $editionBindings] = $this->inClause('legacy_moments.event_edition_id', $editions);

        $inner = $this->visibility->scopeVisibleMoments(LegacyMoment::query(), $viewer)
            ->select('legacy_moments.*')
            ->selectRaw('DATE(legacy_moments.created_at) as feed_day')
            ->selectRaw(
                "(CASE WHEN {$peopleSql} THEN 2 ELSE 0 END + CASE WHEN {$editionSql} THEN 1 ELSE 0 END) as feed_rank",
                [...$peopleBindings, ...$editionBindings],
            )
            ->when($eventsOnly, fn (Builder $q) => $q->whereNotNull('legacy_moments.event_edition_id'));

        return $this->moments->withCardData(
            // Explicit select: withCount() cannot derive "<from>.*" from a subquery.
            LegacyMoment::query()->fromSub($inner->toBase(), 'legacy_moments')->select('legacy_moments.*'),
            $viewer,
        );
    }

    /**
     * Event editions the viewer took part in — "event relevance".
     *
     * @return list<int>
     */
    public function viewerEditionIds(?User $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        $athleteId = $viewer->athlete()->value('id');

        return array_values(EventParticipant::query()
            ->where(fn ($q) => $athleteId !== null
                ? $q->where('athlete_id', $athleteId)->orWhere('user_id', $viewer->id)
                : $q->where('user_id', $viewer->id))
            ->latest('id')
            ->limit(200)
            ->pluck('event_edition_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  literal-string  $column
     * @return array{0: literal-string, 1: list<int>}
     */
    private function inClause(string $column, array $ids): array
    {
        if ($ids === []) {
            return ['1 = 0', []];
        }

        return [$column.' IN ('.implode(', ', array_fill(0, count($ids), '?')).')', $ids];
    }
}
