<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\PortraitDesign;
use App\Services\Portrait\PortraitDesigns;
use App\Services\Portrait\PortraitSpec;
use App\Services\Portrait\PortraitTemplates;
use App\Services\Privacy\ContentPin;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Portarretrato personalization from the app (docs/api/v1.md §Portarretrato):
 * builder data, live server preview, and CRUD of saved designs. Owner only
 * (404 otherwise). Same PortraitDesigns service and PortraitComposer as the
 * web builder, so app, web and production render the same file.
 */
class PortraitDesignController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** `GET me/stories/{publicId}/portrait` — everything the editor needs. */
    public function builder(Request $request, string $publicId, StoryPresenter $presenter, PortraitTemplates $templates, PortraitDesigns $designs): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);
        $view = $presenter->story($story, forOwner: true);
        $spec = PortraitSpec::fromConfig();

        $photos = [];
        foreach ($story->media as $file) {
            /** @var MediaFile $file */
            /** @var object{role: string} $pivot */
            $pivot = $file->getRelationValue('pivot');

            if ($pivot->role !== StoryMediaRole::Photo->value || $file->kind !== MediaKind::Image || ! $file->isServable()) {
                continue;
            }

            $photos[] = [
                'uuid' => $file->uuid,
                'thumb_url' => $file->url('card') ?? $file->url(),
                'url' => $file->url('display') ?? $file->url(),
                'width' => $file->width,
                'height' => $file->height,
                // Cover = first photo of the album (StoryPresenter::story()['cover']).
                'is_cover' => $photos === [],
            ];
        }

        $product = $designs->product();

        return $this->respond([
            'story' => ['public_id' => $story->public_id, 'title' => $view['title'], 'kind' => $view['kind']],
            'photos' => $photos,
            'templates' => array_map(fn (array $t) => [
                'value' => $t['value'],
                'label' => $t['label'],
                'description' => $t['description'],
                'background' => $t['background'],
                'aspect' => $t['aspect'],
            ], $templates->forClient($spec)),
            'default_template' => $templates->defaultKey(),
            'aspect' => $spec->widthPx() / max(1, $spec->heightPx()),
            'defaults' => $designs->defaults($story, $view),
            'product' => $product === null ? null : [
                'name' => $product['name'],
                'variant_uuid' => $product['variant_uuid'],
                'price_minor' => $product['price_minor'],
                'currency' => $product['currency'],
                'available' => $product['available'],
            ],
            'designs' => $this->designsOf($story, $designs),
        ]);
    }

    /**
     * `POST me/stories/{publicId}/portrait/preview` — server render at
     * screen size as a data URI (an <Image> can show it without auth
     * headers). Nothing is stored.
     */
    public function preview(Request $request, string $publicId, PortraitDesigns $designs): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);
        $data = $request->validate([...$designs->rules(), 'width' => ['nullable', 'integer', 'between:160,1000']], $designs->messages());

        $jpeg = $designs->previewJpeg(
            (string) $data['template'],
            $designs->storyPhoto($story, (string) $data['photo_uuid']),
            PortraitDesigns::crop($data['crop'] ?? null),
            is_array($data['fields'] ?? null) ? $data['fields'] : [],
            (int) ($data['width'] ?? 720),
        );

        return $this->respond(['image' => 'data:image/jpeg;base64,'.base64_encode($jpeg)]);
    }

    public function store(Request $request, string $publicId, PortraitDesigns $designs): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);
        $data = $request->validate($designs->rules(), $designs->messages());

        $design = $designs->save($story, $this->sanctumUser($request)->id, $data);

        return $this->respond($designs->toArray($design->load('mediaFile')), __('Guardamos tu portarretrato.'), [], 201);
    }

    public function update(Request $request, string $uuid, PortraitDesigns $designs): JsonResponse
    {
        $design = $this->ownedDesign($request, $uuid);
        $data = $request->validate($designs->rules(partial: true), $designs->messages());

        $design = $designs->save($design->story, $design->user_id, $data, $design);

        return $this->respond($designs->toArray($design->load('mediaFile')), __('Guardamos tu portarretrato.'));
    }

    public function destroy(Request $request, string $uuid): Response
    {
        // Orders keep their own snapshot (OrderItem.metadata.portrait_design).
        $this->ownedDesign($request, $uuid)->delete();

        return response()->noContent();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function designsOf(LegacyStory $story, PortraitDesigns $designs): array
    {
        return PortraitDesign::query()
            ->where('legacy_story_id', $story->id)
            ->with('mediaFile')
            ->latest('updated_at')
            ->get()
            ->map(fn (PortraitDesign $d) => $designs->toArray($d))
            ->values()
            ->all();
    }

    private function ownedStory(Request $request, string $publicId): LegacyStory
    {
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($story->isOwnedBy($this->sanctumUser($request)), 404);
        // Content PIN: a locked memory can't be read or edited (423 CONTENT_LOCKED).
        app(ContentPin::class)->ensureRevealed($story, $request);

        return $story;
    }

    private function ownedDesign(Request $request, string $uuid): PortraitDesign
    {
        $design = PortraitDesign::query()->where('uuid', $uuid)->with('story')->firstOrFail();

        // The design's story must still be the caller's (a transferred tag moves its designs with it).
        abort_unless($design->story !== null && $design->story->isOwnedBy($this->sanctumUser($request)), 404);
        app(ContentPin::class)->ensureRevealed($design->story, $request);

        return $design;
    }
}
