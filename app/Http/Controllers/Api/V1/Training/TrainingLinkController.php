<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Actions\Training\ManageCheerLinks;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreCheerLinkRequest;
use App\Models\CheerLink;
use App\Support\Training\CheerPresenter;
use App\Support\Training\ResolvesOwnCheers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `/api/v1/training/links` — the athlete's "Envíame ánimo" links from the
 * app (list, create, revoke). Same ManageCheerLinks Action as the web
 * page; `url` is always `{APP_URL}/animo/{token}`.
 */
class TrainingLinkController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;
    use ResolvesOwnCheers;

    public function index(Request $request, CheerPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);

        return $this->respond(
            CheerLink::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (CheerLink $link) => $presenter->link($link))
                ->values(),
        );
    }

    public function store(StoreCheerLinkRequest $request, ManageCheerLinks $links, CheerPresenter $presenter): JsonResponse
    {
        $link = $links->create($this->sanctumUser($request), (string) $request->validated('title'), $request->expiresInDays());

        return $this->respond($presenter->link($link), status: 201);
    }

    public function destroy(Request $request, string $uuid, ManageCheerLinks $links): Response
    {
        $user = $this->sanctumUser($request);
        $links->revoke($this->ownLink($user, $uuid), $user);

        return response()->noContent();
    }
}
