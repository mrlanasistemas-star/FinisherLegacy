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
use App\Services\Portrait\PortraitOptions;
use App\Services\Portrait\PortraitPdf;
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
 * Also the Portarretrato builder (docs/renovacion/portarretrato.md):
 * page, server-rendered preview and the print-size download.
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
                'label' => StoryPrintRenderer::layoutLabel($key),
                'paper' => ['width' => $l[1], 'height' => $l[2]],
                'photo' => ['width' => $l[3], 'height' => $l[4]],
            ])->values()->all(),
        ]);
    }

    /**
     * `/mi-legacy/{story}/portarretrato` — the frame builder (deck pp. 9–10):
     * photo → crop at the print aspect → template → words → download. The
     * preview the page shows is rendered here by the same composer as the
     * final file (see previewPortrait), so what you see is what prints.
     */
    public function portrait(Request $request, LegacyStory $story, StoryPresenter $presenter, PortraitTemplates $templates): Response
    {
        $this->authorize('print', $story);
        $data = $presenter->story($story, forOwner: true);
        $spec = PortraitSpec::fromConfig();
        $sources = $this->printSources($story);
        /** @var array<string, mixed> $race */
        $race = $data['race'];

        $photos = [];
        foreach ($data['photos'] as $photo) {
            if ($photo['status'] !== 'ready' || $photo['kind'] !== 'image' || ! isset($sources[$photo['id']])) {
                continue;
            }

            $photos[] = [...$photo, 'source_width' => $sources[$photo['id']]['width'], 'source_height' => $sources[$photo['id']]['height']];
        }

        $bleedMm = max(0.0, (float) config('portrait.print.bleed_mm', 3));

        return Inertia::render('my-legacy/Portrait', [
            'story' => $data,
            'photos' => $photos,
            'spec' => $spec->toArray(),
            'templates' => $templates->forClient($spec),
            'defaultTemplate' => $templates->defaultKey(),
            'defaults' => [
                'name' => $this->text($race['athlete_name'] ?? null) ?? $this->athleteName($story),
                'event' => $race['event_name'] ?? $data['title'],
                'details' => PortraitData::join([$race['location'] ?? null, $this->longDate($race['date'] ?? null)]),
                'time' => $race['time'] ?? null,
                'distance' => $this->text($race['distance'] ?? $race['race'] ?? null),
                'position' => $this->text($race['position'] ?? null),
                'pace' => $this->text($race['pace'] ?? null),
                'bib' => $this->text($race['bib_number'] ?? null),
                'category' => $this->text($race['category'] ?? null),
                'dedication' => $story->dedication,
            ],
            'maxUploadMb' => (int) floor((int) config('legacy.stories.max_photo_bytes') / 1048576),
            'bleedMm' => $bleedMm,
            'isAlbum' => ($data['kind'] ?? null) === 'album',
        ]);
    }

    /**
     * The live preview / template thumbnails: the same render as the final
     * file at screen size (no resolution check). For a photo of the memory
     * the display variant is enough; an own photo arrives already reduced
     * by the browser. Nothing is stored.
     */
    public function previewPortrait(Request $request, LegacyStory $story, PortraitTemplates $templates, PortraitComposer $composer): HttpResponse
    {
        $this->authorize('print', $story);
        $data = $this->validatePortrait($request, $templates, ['width' => ['nullable', 'integer', 'between:160,1000']]);

        $bytes = $request->hasFile('photo')
            ? $this->uploadedBytes($request->file('photo'))
            : $this->storyPhotoBytes($story, (int) $data['photo_id'], preview: true);

        try {
            $result = $composer->compose(
                (string) $data['template'],
                $bytes,
                $this->cropFrom($data),
                $this->portraitData($data),
                PortraitSpec::fromConfig()->scaledToWidth((int) ($data['width'] ?? 720)),
                PortraitOptions::preview(),
            );
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['photo' => __('No pudimos preparar esa foto. Prueba con otra.')]);
        }

        return response($result->jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadPortrait(Request $request, LegacyStory $story, PortraitTemplates $templates, PortraitComposer $composer, PortraitPdf $pdf): HttpResponse
    {
        $this->authorize('print', $story);

        $data = $this->validatePortrait($request, $templates, [
            // `frame`: exact size, ready to slide in. `print_shop`: with bleed.
            'output' => ['nullable', Rule::in(['frame', 'print_shop'])],
            'crop_marks' => ['nullable', 'boolean'],
            'format' => ['nullable', Rule::in(['jpg', 'pdf'])],
        ]);

        $bytes = $request->hasFile('photo')
            ? $this->uploadedBytes($request->file('photo'))
            : $this->storyPhotoBytes($story, (int) $data['photo_id']);

        $printShop = ($data['output'] ?? 'frame') === 'print_shop';
        $options = $printShop ? PortraitOptions::forPrintShop((bool) ($data['crop_marks'] ?? false)) : new PortraitOptions;

        try {
            $result = $composer->compose((string) $data['template'], $bytes, $this->cropFrom($data), $this->portraitData($data), null, $options);
        } catch (PortraitImageTooSmallException $e) {
            throw ValidationException::withMessages([$request->hasFile('photo') ? 'photo' : 'photo_id' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['photo' => __('No pudimos preparar esa foto. Prueba con otra.')]);
        }

        $asPdf = ($data['format'] ?? 'jpg') === 'pdf';
        $title = $story->title ?: ($story->event_name ?? 'recuerdo');
        $name = 'finisher-legacy-portarretrato-'.Str::slug((string) $title).'-'.$data['template'].($printShop ? '-imprenta' : '').($asPdf ? '.pdf' : '.jpg');

        return response($asPdf ? $pdf->wrap($result) : $result->jpeg, 200, [
            'Content-Type' => $asPdf ? 'application/pdf' : 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Portrait-Size' => $result->width.'x'.$result->height,
            'X-Portrait-Trim-Size' => $result->trimWidth.'x'.$result->trimHeight,
            'X-Portrait-Bleed-Px' => (string) $result->bleedPx,
            'X-Portrait-Effective-Dpi' => (string) $result->effectiveDpi,
        ]);
    }

    /**
     * @param  array<string, array<int, mixed>>  $extra
     * @return array<string, mixed>
     */
    private function validatePortrait(Request $request, PortraitTemplates $templates, array $extra = []): array
    {
        return $request->validate([
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
            'distance' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'string', 'max:40'],
            'pace' => ['nullable', 'string', 'max:20'],
            'bib' => ['nullable', 'string', 'max:12'],
            'category' => ['nullable', 'string', 'max:40'],
            'dedication' => ['nullable', 'string', 'max:600'],
            ...$extra,
        ], [
            'photo_id.required_without' => __('Elige una foto de tu recuerdo o sube una.'),
            'photo.required_without' => __('Elige una foto de tu recuerdo o sube una.'),
            'photo.max' => __('La foto es demasiado pesada.'),
            'template.in' => __('Elige una plantilla.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function portraitData(array $data): PortraitData
    {
        return new PortraitData(
            name: $this->text($data['name'] ?? null),
            eventLine: PortraitData::join([$this->text($data['event'] ?? null), $this->text($data['details'] ?? null)]),
            time: $this->text($data['time'] ?? null),
            resultLine: $this->text($data['result'] ?? null),
            dedication: $this->text($data['dedication'] ?? null),
            distance: $this->text($data['distance'] ?? null),
            position: $this->text($data['position'] ?? null),
            pace: $this->text($data['pace'] ?? null),
            bib: $this->text($data['bib'] ?? null),
            category: $this->text($data['category'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function cropFrom(array $data): ?array
    {
        if (! isset($data['crop']) || ! is_array($data['crop'])) {
            return null;
        }

        return [
            'x' => (float) $data['crop']['x'],
            'y' => (float) $data['crop']['y'],
            'w' => (float) $data['crop']['w'],
            'h' => (float) $data['crop']['h'],
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
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
            'photo_id.required_without' => __('Elige una foto de tu recuerdo o sube una.'),
            'photo.required_without' => __('Elige una foto de tu recuerdo o sube una.'),
            'photo.max' => __('La foto es demasiado pesada.'),
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

            throw ValidationException::withMessages(['photo' => __('No pudimos preparar esa foto. Prueba con otra.')]);
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
            throw ValidationException::withMessages(['photo' => __('Sube una foto JPG, PNG o WebP.')]);
        }

        return (string) file_get_contents((string) $file->getRealPath());
    }

    private function storyPhotoBytes(LegacyStory $story, int $pivotId, bool $preview = false): string
    {
        /** @var MediaFile|null $file */
        $file = $story->media()->wherePivot('id', $pivotId)->wherePivot('role', StoryMediaRole::Photo->value)->first();

        if ($file === null || $file->kind !== MediaKind::Image || ! $file->isServable()) {
            throw ValidationException::withMessages(['photo_id' => __('Esa foto todavía se está procesando o ya no existe.')]);
        }

        // The print is cut from the original; a preview only needs the display size.
        $display = $file->resolveVariant('display')['path'] ?? null;
        $path = $preview ? ($display ?? $file->original_path ?? $file->path) : ($file->original_path ?? $display ?? $file->path);
        $bytes = $path !== null ? Storage::disk($file->disk)->get($path) : null;

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['photo_id' => __('No pudimos leer esa foto. Prueba con otra.')]);
        }

        return $bytes;
    }
}
