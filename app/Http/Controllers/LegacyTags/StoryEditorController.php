<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\LegacyTags\UpdateStory;
use App\Enums\StoryMediaRole;
use App\Enums\StoryQuotaKind;
use App\Enums\StoryVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\LegacyTags\StoreStoryMediaRequest;
use App\Http\Requests\LegacyTags\UpdateLegacyStoryRequest;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\PhotoSale;
use App\Models\Sport;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\StoryPhotoQuota;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\StoryRaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * `/mi-legacy/{story}/editar` — the owner builds the memory: title, race
 * data (official values are read-only), dedication, words, visibility,
 * photos (from the device or bought on the site; ordered, captioned,
 * first = cover), videos, voice message, a certificate and an external
 * link. Each kind has its own admin-editable limit (StoryMediaQuota).
 * Every action is LegacyStoryPolicy::update; uploads go through
 * MediaStorage.
 */
class StoryEditorController extends Controller
{
    public function edit(Request $request, LegacyStory $story, StoryPresenter $presenter, StoryPhotoQuota $quota, StoryMediaQuota $mediaQuota): Response
    {
        $this->authorize('update', $story);
        $raceData = StoryRaceData::for($story);

        return Inertia::render('my-legacy/Edit', [
            'story' => $presenter->story($story, forOwner: true),
            // Photo space: used / limit / purchasable expansion (App\Services\Media\StoryPhotoQuota).
            'photoQuota' => fn () => $quota->summary($story),
            // Every kind: photos / videos / audio → limit, used, remaining,
            // can_expand, max_seconds, cap, expansion(s) with the step price
            // and billing period, and the prepaid plan state.
            'quota' => fn () => $mediaQuota->summaryAll($story),
            // Which race fields the linked participation answers officially
            // (read-only, badge "Oficial") and the values shown for them.
            'raceData' => [
                'official' => [...$raceData['official'], 'sport' => $raceData['sport_official']],
                'values' => $raceData['values'],
                'suggested_pace' => $raceData['suggested_pace'],
                'sport' => $raceData['sport'],
            ],
            'sports' => fn () => Sport::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Sport $sport) => ['id' => $sport->id, 'name' => $sport->name])->all(),
            'finishStatuses' => collect(StoryRaceData::finishStatuses())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'hasPurchasedPhotos' => fn () => PhotoSale::query()->grantingAccess()->where('buyer_user_id', $request->user()->id)->exists(),
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
                'bib_number' => $story->bib_number,
                'athlete_display_name' => $story->athlete_display_name,
                'pace' => $story->pace,
                'category' => $story->category,
                'category_position' => $story->category_position,
                'gender_position' => $story->gender_position,
                'finish_status' => $story->finish_status,
                'elevation_gain_m' => $story->elevation_gain_m,
                'sport_id' => $story->sport_id,
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
                    StoryVisibility::Public => __('Cualquiera puede verlo, también desde buscadores.'),
                    StoryVisibility::Unlisted => __('Lo ve quien acerque su teléfono a tu Tag o tenga el enlace. No aparece en buscadores.'),
                    StoryVisibility::Private => __('Solo tú. Puedes compartirlo con alguien especial con un enlace personal.'),
                },
            ], StoryVisibility::cases()),
            'limits' => [
                'max_photos' => (int) config('legacy.stories.max_photos', 12),
                'max_photo_mb' => (int) floor((int) config('legacy.stories.max_photo_bytes') / 1048576),
                'max_audio_mb' => (int) floor((int) config('legacy.stories.max_audio_bytes') / 1048576),
                'max_certificate_mb' => (int) floor((int) config('legacy.stories.max_certificate_bytes') / 1048576),
                'max_video_mb' => (int) floor((int) config('legacy.stories.max_video_bytes') / 1048576),
                'max_audio_seconds' => $mediaQuota->maxSecondsFor($story, StoryQuotaKind::Audio) ?? (int) config('legacy.stories.max_audio_seconds', 180),
                'max_video_seconds' => $mediaQuota->maxSecondsFor($story, StoryQuotaKind::Videos) ?? (int) config('legacy.stories.max_video_seconds', 60),
            ],
        ]);
    }

    public function update(UpdateLegacyStoryRequest $request, LegacyStory $story, UpdateStory $update): RedirectResponse
    {
        $update->handle($story, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardado.')]);

        return back();
    }

    public function storeMedia(StoreStoryMediaRequest $request, LegacyStory $story, ManageStoryMedia $media): RedirectResponse
    {
        $media->add(
            $story,
            $request->user(),
            $request->file('file'),
            StoryMediaRole::from((string) $request->input('role')),
            $request->input('caption'),
            $request->filled('duration_seconds') ? (int) $request->input('duration_seconds') : null,
        );

        return back();
    }

    /**
     * `GET /mi-legacy/{story}/fotos-compradas` (JSON) — the event photos
     * the owner bought on the site and still has access to, for the
     * "Agregar fotos que compré" picker. `in_story` marks the ones this
     * story already holds.
     */
    public function purchasedPhotos(Request $request, LegacyStory $story): JsonResponse
    {
        $this->authorize('update', $story);

        $page = PhotoSale::query()
            ->grantingAccess()
            ->where('buyer_user_id', $request->user()->id)
            ->whereHas('photo')
            ->with('photo.eventEdition.event')
            ->latest('id')
            ->paginate(24);

        $inStory = DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->whereNotNull('photo_sale_id')
            ->pluck('photo_sale_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'data' => collect($page->items())->map(fn (PhotoSale $sale) => [
                'sale_id' => $sale->uuid,
                'thumb_url' => $sale->photo?->thumbUrl(),
                'preview_url' => $sale->photo?->previewUrl(),
                'event_name' => $sale->photo?->eventEdition?->event?->name,
                'purchased_at' => $sale->created_at->toIso8601String(),
                'in_story' => in_array($sale->id, $inStory, true),
            ])->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** `POST /mi-legacy/{story}/media/importar` — adds purchased photos (PhotoSale uuids). */
    public function importMedia(Request $request, LegacyStory $story, ManageStoryMedia $media): RedirectResponse
    {
        $this->authorize('update', $story);
        $data = $request->validate(['sale_ids' => ['required', 'array', 'min:1', 'max:50'], 'sale_ids.*' => ['uuid']]);

        $added = $media->importPurchased($story, $request->user(), array_values($data['sale_ids']));

        Inertia::flash('toast', ['type' => 'success', 'message' => match (count($added)) {
            0 => __('Esas fotos ya estaban en tu recuerdo.'),
            1 => __('Agregamos tu foto. Estará lista en un momento.'),
            default => __('Agregamos :count fotos. Estarán listas en un momento.', ['count' => count($added)]),
        }]);

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
        $data = $request->validate(['order' => ['required', 'array', 'max:500'], 'order.*' => ['integer']]);

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
        $videoNumber = 0;
        /** @var list<string> $temps */
        $temps = [];
        foreach ($story->media as $file) {
            /** @var MediaFile $file */
            /** @var object{role: string} $pivot */
            $pivot = $file->getRelationValue('pivot');

            if (! $file->isServable()) {
                continue;
            }

            $source = $file->original_path ?? $file->resolveVariant('default')['path'] ?? null;

            // Videos can be large: streamed to a temp file, never held in memory.
            if ($pivot->role === StoryMediaRole::Video->value) {
                $stream = $source !== null ? Storage::disk($file->disk)->readStream($source) : null;
                $temp = tempnam(sys_get_temp_dir(), 'flvid');

                if (is_resource($stream) && $temp !== false) {
                    file_put_contents($temp, $stream);
                    fclose($stream);
                    $temps[] = $temp;
                    $zip->addFile($temp, sprintf('videos/%02d.%s', ++$videoNumber, $this->extensionFor((string) $file->mime, $source)));
                }

                continue;
            }

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

        foreach ($temps as $temp) {
            @unlink($temp);
        }

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

        // Rows are [label, value] pairs keyed by a stable identifier: a
        // translated label is never an array key (two labels may translate
        // to the same word and would overwrite each other).
        $rows = [
            'event' => [__('Carrera'), $race['event_name'] ?? null],
            'distance' => [__('Distancia'), $race['distance'] ?? null],
            'time' => [__('Tiempo'), $race['time'] ?? null],
            'position' => [__('Lugar en la clasificación'), $race['position'] ?? null],
            'athlete_name' => [__('Nombre||nombre completo del atleta'), $race['athlete_name'] ?? null],
            'bib_number' => [__('Número de corredor'), $race['bib_number'] ?? null],
            'pace' => [__('Ritmo'), $race['pace'] ?? $race['suggested_pace'] ?? null],
            'category' => [__('Categoría'), $race['category'] ?? null],
            'category_position' => [__('Lugar en su categoría'), $race['category_position'] ?? null],
            'gender_position' => [__('Lugar por rama'), $race['gender_position'] ?? null],
            'elevation_gain' => [__('Desnivel positivo (m)'), isset($race['elevation_gain_m']) ? (string) $race['elevation_gain_m'] : null],
            'sport' => [__('Deporte'), $race['sport'] ?? null],
            'date' => [__('Fecha'), $race['date'] ?? null],
            'location' => [__('Sede'), $race['location'] ?? null],
            'recipient' => [__('Para||destinatario de la dedicatoria'), $view['recipient_label'] ?? null],
        ];

        foreach ($rows as [$label, $value]) {
            if (filled($value)) {
                $lines[] = $label.': '.$value;
            }
        }

        $sections = [
            'dedication' => [__('Dedicatoria'), $view['dedication'] ?? null],
            'body' => [__('Historia'), $view['body'] ?? null],
            'link' => [__('Enlace'), $view['external_url'] ?? null],
        ];

        foreach ($sections as [$label, $value]) {
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
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
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
