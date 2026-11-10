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
use App\Models\MediaFile;
use App\Services\Media\StoryPhotoQuota;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * `/mi-legacy/{story}/editar` — the owner builds the memory: title, race,
 * dedication, words, visibility, photos (ordered, captioned, first =
 * cover), one voice message, a certificate and an external link. Every
 * action is LegacyStoryPolicy::update; uploads go through MediaStorage.
 */
class StoryEditorController extends Controller
{
    public function edit(Request $request, LegacyStory $story, StoryPresenter $presenter, StoryPhotoQuota $quota): Response
    {
        $this->authorize('update', $story);

        return Inertia::render('my-legacy/Edit', [
            'story' => $presenter->story($story, forOwner: true),
            // Photo space: used / limit / purchasable expansion (App\Services\Media\StoryPhotoQuota).
            'photoQuota' => fn () => $quota->summary($story),
            'supportUrl' => route('contact', ['tipo' => 'support']),
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
                'result_position' => $story->result_position,
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

    /**
     * `/mi-legacy/{story}/descargar` — everything the owner put in the
     * memory as one ZIP: photos (original quality), voice message,
     * certificate and the words. Owner only; files are read from the
     * private disk and never exposed through a URL.
     */
    public function archive(Request $request, LegacyStory $story, StoryPresenter $presenter): BinaryFileResponse
    {
        $this->authorize('update', $story);
        $story->loadMissing(StoryPresenter::STORY_RELATIONS);
        $view = $presenter->story($story, forOwner: true);

        $path = tempnam(sys_get_temp_dir(), 'flzip');
        abort_if($path === false, 500);

        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::OVERWRITE) === true, 500);

        $photoNumber = 0;
        foreach ($story->media as $file) {
            /** @var MediaFile $file */
            /** @var object{role: string} $pivot */
            $pivot = $file->getRelationValue('pivot');

            if (! $file->isServable()) {
                continue;
            }

            $source = $file->original_path ?? $file->resolveVariant('default')['path'] ?? null;
            $bytes = $source !== null ? Storage::disk($file->disk)->get($source) : null;

            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $extension = $this->extensionFor((string) $file->mime, $source);
            $name = match ($pivot->role) {
                StoryMediaRole::Photo->value => sprintf('fotos/%02d.%s', ++$photoNumber, $extension),
                StoryMediaRole::Audio->value => 'mensaje-de-voz.'.$extension,
                default => 'certificado.'.$extension,
            };

            $zip->addFromString($name, $bytes);
        }

        $zip->addFromString('recuerdo.txt', $this->storyText($view));
        $zip->close();

        $filename = 'finisher-legacy-'.Str::slug((string) $view['title']).'.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function storyText(array $view): string
    {
        /** @var array<string, string|null> $race */
        $race = $view['race'];
        $lines = [(string) $view['title'], str_repeat('=', mb_strlen((string) $view['title'])), ''];

        foreach ([
            'Carrera' => $race['event_name'] ?? null,
            'Distancia' => $race['distance'] ?? null,
            'Tiempo' => $race['time'] ?? null,
            'Lugar en la clasificación' => $race['position'] ?? null,
            'Fecha' => $race['date'] ?? null,
            'Sede' => $race['location'] ?? null,
            'Para' => $view['recipient_label'] ?? null,
        ] as $label => $value) {
            if (filled($value)) {
                $lines[] = $label.': '.$value;
            }
        }

        foreach (['Dedicatoria' => $view['dedication'] ?? null, 'Historia' => $view['body'] ?? null, 'Enlace' => $view['external_url'] ?? null] as $label => $value) {
            if (filled($value)) {
                $lines[] = '';
                $lines[] = $label.':';
                $lines[] = (string) $value;
            }
        }

        $lines[] = '';
        $lines[] = 'Finisher Legacy · '.$view['url'];

        return implode("\r\n", $lines)."\r\n";
    }

    private function extensionFor(string $mime, ?string $path): string
    {
        $fromPath = $path !== null ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        return $fromPath !== '' ? $fromPath : match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/ogg', 'application/ogg' => 'ogg',
            'audio/webm', 'video/webm' => 'webm',
            default => 'm4a',
        };
    }

    public function destroyMedia(Request $request, LegacyStory $story, int $media, ManageStoryMedia $manage): RedirectResponse
    {
        $this->authorize('update', $story);

        abort_unless($manage->remove($story, $media), 404);

        return back();
    }
}
