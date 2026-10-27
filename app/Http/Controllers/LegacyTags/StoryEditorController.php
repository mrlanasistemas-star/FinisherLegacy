<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\LegacyTags\UpdateStory;
use App\Enums\StoryMediaRole;
use App\Enums\StoryVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\LegacyTags\StoreStoryMediaRequest;
use App\Http\Requests\LegacyTags\UpdateLegacyStoryRequest;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/mi-legacy/{story}/editar` — the owner builds the memory: title, race,
 * dedication, words, visibility, photos (ordered, captioned, first =
 * cover), one voice message, a certificate and an external link. Every
 * action is LegacyStoryPolicy::update; uploads go through MediaStorage.
 */
class StoryEditorController extends Controller
{
    public function edit(Request $request, LegacyStory $story, StoryPresenter $presenter): Response
    {
        $this->authorize('update', $story);

        return Inertia::render('my-legacy/Edit', [
            'story' => $presenter->story($story, forOwner: true),
            'fields' => [
                'title' => $story->title,
                'recipient_label' => $story->recipient_label,
                'dedication' => $story->dedication,
                'body' => $story->body,
                'external_url' => $story->external_url,
                'visibility' => $story->visibility->value,
                'race_mode' => $story->event_participant_id !== null ? 'participation' : ($story->event_name !== null ? 'manual' : 'later'),
                'event_participant_id' => $story->event_participant_id,
                'event_name' => $story->event_name,
                'distance' => $story->distance,
                'result_time' => $story->result_time,
                'race_date' => $story->race_date?->toDateString(),
                'location' => $story->location,
            ],
            'participations' => EventParticipant::query()
                ->where('athlete_id', $story->athlete_id)
                ->with(['eventEdition.event', 'eventRace', 'result'])
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (EventParticipant $p) => $presenter->participationOption($p))
                ->values()
                ->all(),
            'shares' => $presenter->shares($story),
            'visibilityOptions' => array_map(fn (StoryVisibility $v) => [
                'value' => $v->value,
                'label' => $v->label(),
                'help' => match ($v) {
                    StoryVisibility::Public => 'Cualquiera puede verlo, también desde buscadores.',
                    StoryVisibility::Unlisted => 'Lo ve quien acerque su teléfono a tu tag o tenga el enlace. No aparece en buscadores.',
                    StoryVisibility::Private => 'Solo tú. Puedes compartirlo con alguien especial con un enlace personal.',
                },
            ], StoryVisibility::cases()),
            'limits' => [
                'max_photos' => (int) config('legacy.stories.max_photos', 12),
                'max_photo_mb' => (int) floor((int) config('legacy.stories.max_photo_bytes') / 1048576),
                'max_audio_mb' => (int) floor((int) config('legacy.stories.max_audio_bytes') / 1048576),
                'max_certificate_mb' => (int) floor((int) config('legacy.stories.max_certificate_bytes') / 1048576),
                'max_audio_seconds' => (int) config('legacy.stories.max_audio_seconds', 180),
            ],
        ]);
    }

    public function update(UpdateLegacyStoryRequest $request, LegacyStory $story, UpdateStory $update): RedirectResponse
    {
        $update->handle($story, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardado.']);

        return back();
    }

    public function storeMedia(StoreStoryMediaRequest $request, LegacyStory $story, ManageStoryMedia $media): RedirectResponse
    {
        $media->add($story, $request->user(), $request->file('file'), StoryMediaRole::from((string) $request->input('role')), $request->input('caption'));

        return back();
    }

    public function updateMedia(Request $request, LegacyStory $story, int $media, ManageStoryMedia $manage): RedirectResponse
    {
        $this->authorize('update', $story);
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:280']]);

        $manage->caption($story, $media, $data['caption'] ?? null);

        return back();
    }

    public function reorderMedia(Request $request, LegacyStory $story, ManageStoryMedia $manage): RedirectResponse
    {
        $this->authorize('update', $story);
        $data = $request->validate(['order' => ['required', 'array', 'max:50'], 'order.*' => ['integer']]);

        $manage->reorder($story, array_map('intval', $data['order']));

        return back();
    }

    public function destroyMedia(Request $request, LegacyStory $story, int $media, ManageStoryMedia $manage): RedirectResponse
    {
        $this->authorize('update', $story);

        abort_unless($manage->remove($story, $media), 404);

        return back();
    }
}
