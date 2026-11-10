<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Enums\StoryMediaRole;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\PhotoSale;
use App\Services\Media\StoryMediaQuota;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Photos, videos and voice messages of the owner's memory from the app
 * (docs/api/v1.md §Editor del recuerdo): list with the per-kind quota,
 * upload, delete, reorder, and "add photos I bought". Everything goes
 * through ManageStoryMedia — the same limits, locks and pipeline as the
 * web editor. Owner only (404 otherwise). A full kind answers 422
 * MEDIA_LIMIT_REACHED (PhotoQuotaExceededException).
 */
class StoryMediaController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** Roles the app manages here (the certificate stays a web-only extra). */
    private const array ROLES = ['photo', 'video', 'audio'];

    public function index(Request $request, string $publicId, StoryPresenter $presenter, StoryMediaQuota $quota): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);

        return $this->respond($presenter->apiMedia($story), null, ['quota' => $quota->summaryAll($story)]);
    }

    public function store(Request $request, string $publicId, ManageStoryMedia $media, StoryPresenter $presenter, StoryMediaQuota $quota): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);

        $maxKb = (int) ceil(max(
            (int) config('legacy.stories.max_photo_bytes'),
            (int) config('legacy.stories.max_audio_bytes'),
            (int) config('legacy.stories.max_video_bytes'),
        ) / 1024);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'role' => ['required', Rule::in(self::ROLES)],
            'caption' => ['nullable', 'string', 'max:280'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ], [
            'file.required' => __('Elige un archivo.'),
            'file.max' => __('El archivo es demasiado pesado. Prueba con uno más ligero.'),
            'file.uploaded' => __('No pudimos recibir el archivo. Intenta de nuevo.'),
        ]);

        $file = $media->add(
            $story,
            $this->sanctumUser($request),
            $request->file('file'),
            StoryMediaRole::from((string) $data['role']),
            $data['caption'] ?? null,
            isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
        );

        return $this->respond($this->item($story, $presenter, $file), __('Archivo subido.'), ['quota' => $quota->summaryAll($story)], 201);
    }

    public function destroy(Request $request, string $publicId, string $uuid, ManageStoryMedia $media): Response
    {
        $story = $this->ownedStory($request, $publicId);

        abort_unless($media->removeByUuid($story, $uuid), 404);

        return response()->noContent();
    }

    public function reorder(Request $request, string $publicId, ManageStoryMedia $media): Response
    {
        $story = $this->ownedStory($request, $publicId);
        $data = $request->validate(['order' => ['required', 'array', 'max:500'], 'order.*' => ['uuid']]);

        $media->reorderByUuids($story, array_values($data['order']));

        return response()->noContent();
    }

    /** Adds event photos the owner bought on the site (PhotoSale uuids). */
    public function import(Request $request, string $publicId, ManageStoryMedia $media, StoryPresenter $presenter, StoryMediaQuota $quota): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);
        $data = $request->validate(['sale_ids' => ['required', 'array', 'min:1', 'max:50'], 'sale_ids.*' => ['uuid']]);

        $files = $media->importPurchased($story, $this->sanctumUser($request), array_values($data['sale_ids']));
        $story->unsetRelation('media');

        return $this->respond(
            array_values(array_filter(array_map(fn (MediaFile $file) => $this->item($story, $presenter, $file), $files))),
            $files === [] ? __('Esas fotos ya estaban en tu recuerdo.') : __('Fotos agregadas a tu recuerdo.'),
            ['quota' => $quota->summaryAll($story)],
            201,
        );
    }

    /**
     * `GET /api/v1/me/purchased-photos` — the event photos this user
     * bought and still has access to, newest first, with the stories that
     * already hold each one.
     */
    public function purchased(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $page = PhotoSale::query()
            ->grantingAccess()
            ->where('buyer_user_id', $user->id)
            ->whereHas('photo')
            ->with('photo.eventEdition.event')
            ->latest('id')
            ->paginate(30);

        $inStories = DB::table('legacy_story_media')
            ->join('legacy_stories', 'legacy_stories.id', '=', 'legacy_story_media.legacy_story_id')
            ->whereIn('legacy_story_media.photo_sale_id', collect($page->items())->pluck('id'))
            ->whereNull('legacy_stories.deleted_at')
            ->get(['legacy_story_media.photo_sale_id', 'legacy_stories.public_id'])
            ->groupBy('photo_sale_id');

        return $this->respond(
            collect($page->items())->map(fn (PhotoSale $sale) => [
                'sale_id' => $sale->uuid,
                'thumb_url' => $sale->photo?->thumbUrl(),
                'preview_url' => $sale->photo?->previewUrl(),
                'event_name' => $sale->photo?->eventEdition?->event?->name,
                'purchased_at' => $sale->created_at->toIso8601String(),
                'already_in_story_ids' => $inStories->get($sale->id, collect())->pluck('public_id')->values()->all(),
            ])->values()->all(),
            null,
            ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(LegacyStory $story, StoryPresenter $presenter, MediaFile $file): ?array
    {
        $story->unsetRelation('media');

        foreach ($presenter->apiMedia($story) as $item) {
            if ($item['uuid'] === $file->uuid) {
                return $item;
            }
        }

        return null;
    }

    private function ownedStory(Request $request, string $publicId): LegacyStory
    {
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($story->isOwnedBy($this->sanctumUser($request)), 404);

        return $story;
    }
}
