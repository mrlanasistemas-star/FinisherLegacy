<?php

namespace App\Queries\Social;

use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Support\Collection;

/**
 * The latest comments shown under each post of a feed page — ONE query
 * for the whole page (ROW_NUMBER per post, MySQL 8 / SQLite ≥ 3.25), set
 * as the `previewComments` relation so MomentResource renders it.
 * Same filters as the detail thread: no moderated comments, nobody the
 * viewer blocked or was blocked by.
 */
class CommentPreviews
{
    public const int PER_POST = 2;

    public function __construct(private readonly SocialVisibility $visibility) {}

    /**
     * @param  iterable<int, LegacyMoment>  $moments
     */
    public function attach(iterable $moments, ?User $viewer): void
    {
        $moments = collect($moments);
        $ids = $moments->filter(fn (LegacyMoment $m) => (int) ($m->comments_count ?? 0) > 0)->map(fn (LegacyMoment $m) => $m->id)->all();
        $ids = array_values($ids);

        $comments = $ids === [] ? collect() : $this->load($ids, $viewer);

        $moments->each(fn (LegacyMoment $moment) => $moment->setRelation(
            'previewComments',
            $comments->get($moment->id, (new LegacyMomentComment)->newCollection()),
        ));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, \Illuminate\Database\Eloquent\Collection<int, LegacyMomentComment>>
     */
    private function load(array $ids, ?User $viewer): Collection
    {
        $hidden = $this->visibility->hiddenUserIds($viewer);
        $table = (new LegacyMomentComment)->getTable();

        $ranked = LegacyMomentComment::query()
            ->select("{$table}.*")
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY legacy_moment_comments.legacy_moment_id ORDER BY legacy_moment_comments.id DESC) as preview_rank')
            ->whereIn("{$table}.legacy_moment_id", $ids)
            ->whereNull("{$table}.hidden_at")
            ->when($hidden !== [], fn ($q) => $q->whereNotIn("{$table}.user_id", $hidden))
            ->whereHas('author');

        return LegacyMomentComment::query()
            ->fromSub($ranked->toBase(), $table)
            ->select("{$table}.*")
            ->where('preview_rank', '<=', self::PER_POST)
            ->with('author.athleteProfile')
            ->orderBy('id')
            ->get()
            ->groupBy('legacy_moment_id');
    }
}
