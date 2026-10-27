<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LegacyTags\ActivateTag;
use App\Actions\LegacyTags\RecordTagScan;
use App\Actions\LegacyTags\UpdateStory;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LegacyTags\StoryRaceRules;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\User;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\TagActivationState;
use App\Support\LegacyTags\TagCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Finisher Legacy Tag for the app (docs/api/v1.md §Finisher Legacy Tag).
 * Same ActivateTag / TagActivationState / StoryPresenter as the web —
 * never a second implementation. Errors via the ApiException renderer:
 * TAG_ALREADY_ACTIVATED (409), TAG_NOT_ACTIVATABLE (422, details.reason).
 */
class LegacyTagController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** Public (throttle tag-open). Optional Bearer → owned_by_me. No PII. */
    public function show(Request $request, string $code, StoryPresenter $presenter, RecordTagScan $scans): JsonResponse
    {
        $tag = $this->findTag($code);
        $viewer = $request->user('sanctum');
        $viewer = $viewer instanceof User ? $viewer : null;
        $state = TagActivationState::for($tag, $viewer);
        $source = TagAccessSource::tryFrom((string) $request->query('src', '')) ?? TagAccessSource::Nfc;

        $scans->handle($tag, TagScanEvent::TagOpened, $source, $viewer, $request, $state->state === TagActivationState::MINE);

        $story = $tag->story;
        $storyVisible = $story !== null && Gate::forUser($viewer)->allows('view', $story);

        return $this->respond([
            'code' => $tag->public_code,
            'display_code' => $tag->displayCode(),
            'status' => $state->state,
            'reason' => $state->reason,
            'message' => $state->message(),
            'can_activate' => $state->canActivate(),
            'owned_by_me' => $state->state === TagActivationState::MINE,
            'tag' => ($summary = $presenter->tag($tag)) === null ? null : [
                'collection' => $summary['collection'],
                'design' => [
                    'product_name' => $summary['product_name'],
                    'variant_name' => $summary['variant_name'],
                    'image_url' => $summary['image_url'],
                ],
            ],
            'story_url' => $storyVisible ? $story->publicUrl() : null,
            'story_public_id' => $storyVisible ? $story->public_id : null,
            'activate_url' => route('activate.show', $tag->public_code),
        ]);
    }

    public function activate(Request $request, string $code, ActivateTag $activate, UpdateStory $update, StoryPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate([
            ...StoryRaceRules::rules(required: false),
            'source' => ['nullable', Rule::enum(TagAccessSource::class)],
        ], StoryRaceRules::messages());

        $tag = $this->findTag($code);
        $story = $activate->handle($tag, $user, TagAccessSource::tryFrom((string) ($data['source'] ?? '')) ?? TagAccessSource::Nfc);

        if (in_array($data['race_mode'] ?? null, ['participation', 'manual'], true)) {
            $update->applyRace($story, (string) $data['race_mode'], $data);
            $story->completed_at ??= now();
            $story->save();
        }

        return $this->respond($presenter->apiStory($story->fresh() ?? $story, forOwner: true), '¡Listo! Tu Finisher Legacy ya es tuyo.');
    }

    public function myStories(Request $request, StoryPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $athleteId = $user->athlete()->value('id');

        $stories = $athleteId === null ? collect() : LegacyStory::query()
            ->where('athlete_id', $athleteId)
            ->with(StoryPresenter::STORY_RELATIONS)
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->respond($stories->map(fn (LegacyStory $s) => $presenter->apiStory($s, forOwner: true))->values()->all());
    }

    public function story(Request $request, string $publicId, StoryPresenter $presenter): JsonResponse
    {
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();
        $viewer = $request->user('sanctum');
        $viewer = $viewer instanceof User ? $viewer : null;

        abort_unless(Gate::forUser($viewer)->allows('view', $story), 404);

        return $this->respond($presenter->apiStory($story, forOwner: $story->isOwnedBy($viewer)));
    }

    private function findTag(string $code): LegacyTag
    {
        $normalized = TagCode::normalize($code);
        abort_if($normalized === null, 404);

        return LegacyTag::query()->where('public_code', $normalized)->firstOrFail();
    }
}
