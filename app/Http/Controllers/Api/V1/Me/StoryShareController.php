<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\LegacyTags\ManageStoryShares;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use App\Services\Privacy\ContentPin;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recipient links ("Para mamá") from the app — the same ManageStoryShares
 * as the web editor (LegacyTags\StoryShareController), so a link made on
 * the phone opens `/legacy/para/{token}` exactly like one made on the web.
 * Owner only (404 otherwise).
 */
class StoryShareController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request, string $publicId, StoryPresenter $presenter): JsonResponse
    {
        return $this->respond($presenter->shares($this->ownedStory($request, $publicId)));
    }

    public function store(Request $request, string $publicId, ManageStoryShares $shares, StoryPresenter $presenter): JsonResponse
    {
        $story = $this->ownedStory($request, $publicId);
        $data = $request->validate(['label' => ['required', 'string', 'max:80']], [
            'label.required' => __('¿Para quién es? Por ejemplo: Mamá.'),
        ]);

        $share = $shares->create($story, $this->sanctumUser($request), (string) $data['label']);
        $item = collect($presenter->shares($story))->firstWhere('id', $share->id);

        return $this->respond($item, __('Enlace listo para :name.', ['name' => $share->label]), [], 201);
    }

    public function destroy(Request $request, string $publicId, int $share, ManageStoryShares $shares): Response
    {
        $story = $this->ownedStory($request, $publicId);
        $row = LegacyStoryShare::query()->where('legacy_story_id', $story->id)->whereKey($share)->firstOrFail();

        $shares->revoke($row, $this->sanctumUser($request));

        return response()->noContent();
    }

    private function ownedStory(Request $request, string $publicId): LegacyStory
    {
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($story->isOwnedBy($this->sanctumUser($request)), 404);
        // Content PIN: a locked memory can't be read or edited (423 CONTENT_LOCKED).
        app(ContentPin::class)->ensureRevealed($story, $request);

        return $story;
    }
}
