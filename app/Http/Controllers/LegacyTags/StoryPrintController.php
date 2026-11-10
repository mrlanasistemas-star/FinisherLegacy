<?php

namespace App\Http\Controllers\LegacyTags;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Services\LegacyTags\StoryPrintRenderer;
use App\Services\Media\MediaUploadRules;
use App\Services\Portrait\PortraitComposer;
use App\Services\Portrait\PortraitData;
use App\Services\Portrait\PortraitImageTooSmallException;
use App\Services\Portrait\PortraitSpec;
use App\Services\Portrait\PortraitTemplates;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * `/mi-legacy/{story}/imprimir` — print template MVP (§23). Owner only
 * (LegacyStoryPolicy::print): it reads the owner's private files straight
 * from the media disk. The uploaded "own photo" is never stored.
 */
class StoryPrintController extends Controller
{
    public function show(LegacyStory $story, StoryPresenter $presenter): Response
    {
        $this->authorize('print', $story);
        $data = $presenter->story($story, forOwner: true);

        return Inertia::render('my-legacy/Print', [
            'story' => $data,
            'photos' => array_values(array_filter($data['photos'], fn (array $p) => $p['status'] === 'ready' && $p['kind'] === 'image')),
            'layouts' => collect(StoryPrintRenderer::LAYOUTS)->map(fn (array $l, string $key) => [
                'value' => $key,
                'label' => $l[0],
                'paper' => ['width' => $l[1], 'height' => $l[2]],
                'photo' => ['width' => $l[3], 'height' => $l[4]],
            ])->values()->all(),
        ]);
    }

    /**
     * `/mi-legacy/{story}/portarretrato` — the frame builder (deck pp. 9–10):
     * photo, crop at the print aspect, template, live preview, download.
     */
    public function portrait(Request $request, LegacyStory $story, StoryPresenter $presenter, PortraitTemplates $templates): Response
    {
        $this->authorize('print', $story);
        $data = $presenter->story($story, forOwner: true);
        $spec = PortraitSpec::fromConfig();
        $sources = $this->printSources($story);
        $race = $data['race'];

        $photos = [];
        foreach ($data['photos'] as $photo) {
            if ($photo['status'] !== 'ready' || $photo['kind'] !== 'image' || ! isset($sources[$photo['id']])) {
                continue;
            }

            $photos[] = [...$photo, 'source_width' => $sources[$photo['id']]['width'], 'source_height' => $sources[$photo['id']]['height']];
        }

        return Inertia::render('my-legacy/Portrait', [
            'story' => $data,
            'photos' => $photos,
            'spec' => $spec->toArray(),
            'templates' => $templates->forClient($spec),
            'defaultTemplate' => $templates->defaultKey(),
            'defaults' => [
                'name' => $this->athleteName($story),
                'event' => $race['event_name'] ?? $data['title'],
                'details' => PortraitData::join([$race['location'], $this->longDate($race['date'])]),
                'time' => $race['time'],
                'result' => PortraitData::join([$race['distance'] ?? $race['race'], filled($race['position'] ?? null) ? 'Lugar '.$race['position'] : null]),
                'dedication' => $story->dedication,
            ],
            'maxUploadMb' => (int) floor((int) config('legacy.stories.max_photo_bytes') / 1048576),
            'marks' => [
                'gold' => asset('images/brand/logo/fl-monograma.png'),
                'black' => asset('images/brand/logo/fl-monograma.png'),
                'white' => asset('images/brand/logo/fl-monograma.png'),
            ],
        ]);
    }

    public function downloadPortrait(Request $request, LegacyStory $story, PortraitTemplates $templates, PortraitComposer $composer): HttpResponse
    {
        $this->authorize('print', $story);

        $data = $request->validate([
            'template' => ['required', Rule::in($templates->keys())],
            'photo_id' => ['nullable', 'required_without:photo', 'integer'],
            'photo' => ['nullable', 'required_without:photo_id', 'file', 'max:'.(int) ceil((int) config('legacy.stories.max_photo_bytes') / 1024)],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.y' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.w' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'crop.h' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'name' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:160'],
            'time' => ['nullable', 'string', 'max:20'],
            'result' => ['nullable', 'string', 'max:80'],
            'dedication' => ['nullable', 'string', 'max:600'],
        ], [
            'photo_id.required_without' => 'Elige una foto de tu recuerdo o sube una.',
            'photo.required_without' => 'Elige una foto de tu recuerdo o sube una.',
            'photo.max' => 'La foto es demasiado pesada.',
            'template.in' => 'Elige una plantilla.',
        ]);

        $bytes = $request->hasFile('photo')
            ? $this->uploadedBytes($request->file('photo'))
            : $this->storyPhotoBytes($story, (int) $data['photo_id']);

        $crop = isset($data['crop']) ? [
            'x' => (float) $data['crop']['x'],
            'y' => (float) $data['crop']['y'],
            'w' => (float) $data['crop']['w'],
            'h' => (float) $data['crop']['h'],
        ] : null;

        $text = new PortraitData(
            name: $data['name'] ?? null,
            eventLine: PortraitData::join([$data['event'] ?? null, $data['details'] ?? null]),
            time: $data['time'] ?? null,
            resultLine: $data['result'] ?? null,
            dedication: $data['dedication'] ?? null,
        );

        try {
            $result = $composer->compose((string) $data['template'], $bytes, $crop, $text);
        } catch (PortraitImageTooSmallException $e) {
            throw ValidationException::withMessages([$request->hasFile('photo') ? 'photo' : 'photo_id' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['photo' => 'No pudimos preparar esa foto. Prueba con otra.']);
        }

        $title = $story->title ?: ($story->event_name ?? 'recuerdo');
        $name = 'finisher-legacy-portarretrato-'.Str::slug((string) $title).'-'.$data['template'].'.jpg';

        return response($result->jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Portrait-Size' => $result->width.'x'.$result->height,
            'X-Portrait-Effective-Dpi' => (string) $result->effectiveDpi,
        ]);
    }

    /**
     * Pixel size of what the portrait will really be cut from (the original
     * when kept, else the largest variant), keyed by pivot id.
     *
     * @return array<int, array{width: int, height: int}>
     */
    private function printSources(LegacyStory $story): array
    {
        $out = [];

        foreach ($story->media as $file) {
            /** @var MediaFile $file */
            /** @var object{id: int, role: string} $pivot */
            $pivot = $file->getRelationValue('pivot');

            if ($pivot->role !== StoryMediaRole::Photo->value || $file->kind !== MediaKind::Image) {
                continue;
            }

            if ($file->original_path !== null && $file->width !== null && $file->height !== null) {
                $out[(int) $pivot->id] = ['width' => (int) $file->width, 'height' => (int) $file->height];

                continue;
            }

            $variant = $file->variants['display'] ?? collect($file->variants ?? [])->sortByDesc('width')->first();

            if (is_array($variant) && isset($variant['width'], $variant['height'])) {
                $out[(int) $pivot->id] = ['width' => (int) $variant['width'], 'height' => (int) $variant['height']];
            }
        }

        return $out;
    }

    private function athleteName(LegacyStory $story): ?string
    {
        $athlete = $story->athlete;

        if ($athlete === null) {
            return null;
        }

        $name = filled($athlete->full_name) ? $athlete->full_name : trim($athlete->first_name.' '.$athlete->last_name);

        return $name !== '' ? $name : null;
    }

    private function longDate(?string $date): ?string
    {
        return $date !== null ? Carbon::parse($date)->settings(['locale' => 'es'])->isoFormat('D [de] MMMM YYYY') : null;
    }

    public function download(Request $request, LegacyStory $story, StoryPresenter $presenter, StoryPrintRenderer $renderer): HttpResponse
    {
        $this->authorize('print', $story);

        $data = $request->validate([
            'layout' => ['required', Rule::in(array_keys(StoryPrintRenderer::LAYOUTS))],
            'photo_id' => ['nullable', 'required_without:photo', 'integer'],
            'photo' => ['nullable', 'required_without:photo_id', 'file', 'max:'.(int) ceil((int) config('legacy.stories.max_photo_bytes') / 1024)],
            'dedication' => ['nullable', 'string', 'max:600'],
        ], [
            'photo_id.required_without' => 'Elige una foto de tu recuerdo o sube una.',
            'photo.required_without' => 'Elige una foto de tu recuerdo o sube una.',
            'photo.max' => 'La foto es demasiado pesada.',
        ]);

        $bytes = $request->hasFile('photo')
            ? $this->uploadedBytes($request->file('photo'))
            : $this->storyPhotoBytes($story, (int) $data['photo_id']);

        $view = $presenter->story($story, forOwner: true);
        $race = $view['race'];

        try {
            $pdf = $renderer->render((string) $data['layout'], $bytes, [
                'title' => (string) $view['title'],
                'event_name' => $race['event_name'],
                'race' => $race['race'],
                'distance' => $race['distance'],
                'time' => $race['time'],
                'date' => $race['date'] !== null ? Carbon::parse($race['date'])->settings(['locale' => 'es'])->isoFormat('D [de] MMMM YYYY') : null,
                'location' => $race['location'],
                'dedication' => array_key_exists('dedication', $data) && $data['dedication'] !== null ? $data['dedication'] : $story->dedication,
                'recipient_label' => $story->recipient_label,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['photo' => 'No pudimos preparar esa foto. Prueba con otra.']);
        }

        $name = 'finisher-legacy-'.Str::slug((string) $view['title']).'-'.$data['layout'].'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function uploadedBytes(UploadedFile $file): string
    {
        // Sniffed from the bytes, never the extension.
        if (! in_array((string) $file->getMimeType(), MediaUploadRules::IMAGE_MIMES, true)) {
            throw ValidationException::withMessages(['photo' => 'Sube una foto JPG, PNG o WebP.']);
        }

        return (string) file_get_contents((string) $file->getRealPath());
    }

    private function storyPhotoBytes(LegacyStory $story, int $pivotId): string
    {
        /** @var MediaFile|null $file */
        $file = $story->media()->wherePivot('id', $pivotId)->wherePivot('role', StoryMediaRole::Photo->value)->first();

        if ($file === null || $file->kind !== MediaKind::Image || ! $file->isServable()) {
            throw ValidationException::withMessages(['photo_id' => 'Esa foto todavía se está procesando o ya no existe.']);
        }

        $path = $file->original_path ?? $file->resolveVariant('display')['path'] ?? $file->path;
        $bytes = $path !== null ? Storage::disk($file->disk)->get($path) : null;

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['photo_id' => 'No pudimos leer esa foto. Prueba con otra.']);
        }

        return $bytes;
    }
}
