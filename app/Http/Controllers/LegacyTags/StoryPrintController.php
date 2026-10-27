<?php

namespace App\Http\Controllers\LegacyTags;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Services\LegacyTags\StoryPrintRenderer;
use App\Services\Media\MediaUploadRules;
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
