<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\LegacyTags\UpdateStory;
use App\Enums\StoryVisibility;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LegacyTags\StoryRaceRules;
use App\Models\LegacyStory;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner edits the memory from the app (docs/api/v1.md §Editor del
 * recuerdo): `GET|PATCH /api/v1/me/stories/{publicId}`. Same UpdateStory
 * and StoryPresenter as the web editor. Owner only — anyone else gets 404
 * (the endpoint never confirms that a story exists).
 */
class StoryEditorController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function show(Request $request, string $publicId, StoryPresenter $presenter): JsonResponse
    {
        return $this->respond($presenter->apiEditableStory($this->ownedStory($request, $publicId)));
    }

    public function update(Request $request, string $publicId, UpdateStory $update, StoryPresenter $presenter): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'event_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'distance' => ['sometimes', 'nullable', 'string', 'max:60'],
            'result_time' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d{1,3}:\d{2}(:\d{2})?(\.\d{1,3})?$/'],
            'race_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'location' => ['sometimes', 'nullable', 'string', 'max:160'],
            ...array_map(fn (array $rules) => ['sometimes', ...$rules], StoryRaceRules::extraRules()),
            'recipient_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'dedication' => ['sometimes', 'nullable', 'string', 'max:600'],
            'body' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'external_url' => ['sometimes', 'nullable', 'url:https,http', 'max:512'],
            'visibility' => ['sometimes', Rule::enum(StoryVisibility::class)],
        ], [...StoryRaceRules::messages(), 'external_url.url' => 'El enlace debe empezar con https://']);

        $story = $update->handle($story, $this->sanctumUser($request), $data);

        return $this->respond($presenter->apiEditableStory($story->fresh() ?? $story), 'Guardado.');
    }

    private function ownedStory(Request $request, string $publicId): LegacyStory
    {
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($story->isOwnedBy($this->sanctumUser($request)), 404);

        return $story;
    }
}
