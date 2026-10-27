<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ManageStoryShares;
use App\Actions\LegacyTags\RecordTagScan;
use App\Enums\StoryVisibility;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use App\Support\LegacyTags\StoryPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The emotional Legacy view — what opens when someone taps the tag.
 * `/legacy/{publicId}` follows LegacyStoryPolicy; `/legacy/para/{token}`
 * is a recipient link that opens it without an account (even private).
 * Only public stories are indexable (meta robots + X-Robots-Tag, so it
 * holds without SSR); stories are never in the sitemap.
 */
class StoryController extends Controller
{
    public function show(Request $request, LegacyStory $story, StoryPresenter $presenter, RecordTagScan $scans): HttpResponse
    {
        $user = $request->user();

        if (! Gate::forUser($user)->allows('view', $story)) {
            return $this->noindex($request, Inertia::render('legacy/Private', [
                'tag' => $presenter->tag($story->tag),
                'loginUrl' => route('login'),
            ]));
        }

        $isOwner = $story->isOwnedBy($user);
        $via = TagAccessSource::tryFrom((string) $request->session()->get('legacy.via', '')) ?? TagAccessSource::Link;

        if ($story->tag !== null) {
            $scans->handle($story->tag, TagScanEvent::LegacyViewed, $via, $user, $request, $isOwner);
        }

        $page = Inertia::render('legacy/Story', [
            'story' => $presenter->story($story, $isOwner),
            'mode' => $isOwner ? 'owner' : 'visitor',
            'recipientLabel' => null,
            'noindex' => $story->visibility !== StoryVisibility::Public,
            'shares' => $isOwner ? $presenter->shares($story) : [],
        ]);

        return $story->visibility === StoryVisibility::Public
            ? $page->toResponse($request)
            : $this->noindex($request, $page);
    }

    public function share(Request $request, string $token, StoryPresenter $presenter, ManageStoryShares $shares): HttpResponse
    {
        $share = LegacyStoryShare::query()->where('token', $token)->with('story')->first();
        abort_if($share === null || $share->story === null, 404);

        if (! $share->isActive()) {
            return $this->noindex($request, Inertia::render('legacy/Unavailable', [
                'kind' => 'revoked',
                'title' => 'Este enlace ya no está disponible',
                'message' => 'Quien te lo compartió lo desactivó. Si crees que es un error, pídele un enlace nuevo.',
            ]));
        }

        $shares->opened($share, $request);

        return $this->noindex($request, Inertia::render('legacy/Story', [
            'story' => $presenter->story($share->story),
            'mode' => 'recipient',
            'recipientLabel' => $share->label,
            'noindex' => true,
            'shares' => [],
        ]));
    }

    private function noindex(Request $request, Response $page): HttpResponse
    {
        $response = $page->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
