<?php

namespace App\Http\Controllers\LegacyTags;

use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/mi-legacy` — the athlete's library of memories, grouped by year
 * (MI LEGACY → 2026: HYROX, Cozumel…). Each card shows the physical tag
 * (collection/design) and the cover photo. "Mi legado" (/dashboard) stays.
 */
class MyLegacyController extends Controller
{
    public function __invoke(Request $request, StoryPresenter $presenter): Response
    {
        $athleteId = $request->user()->athlete()->value('id');

        $stories = $athleteId === null ? collect() : LegacyStory::query()
            ->where('athlete_id', $athleteId)
            ->with(StoryPresenter::STORY_RELATIONS)
            ->latest('id')
            ->limit(200)
            ->get();

        $cards = $stories->map(function (LegacyStory $story) use ($presenter) {
            $data = $presenter->story($story, forOwner: true);

            return [
                'public_id' => $data['public_id'],
                'title' => $data['title'],
                'race' => $data['race'],
                'cover' => $data['cover'],
                'tag' => $data['tag'],
                'visibility' => $data['visibility'],
                'visibility_label' => $data['visibility_label'],
                'year' => $data['year'],
                'photo_count' => count($data['photos']),
                'has_audio' => $data['audio'] !== null,
                'needs_details' => ! $presenter->hasRace($story) && $story->completed_at === null,
                'sort' => $data['race']['date'] ?? $data['created_at'],
            ];
        });

        $years = $cards->groupBy('year')
            ->sortKeysDesc()
            ->map(fn ($items, $year) => [
                'year' => (int) $year,
                'stories' => $items->sortByDesc('sort')->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('my-legacy/Index', [
            'years' => $years,
            'total' => $cards->count(),
        ]);
    }
}
