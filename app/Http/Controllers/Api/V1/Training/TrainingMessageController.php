<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Actions\Training\ManageCheerMessages;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\CheerMessage;
use App\Queries\Training\ListCheerMessages;
use App\Support\Training\CheerPresenter;
use App\Support\Training\ResolvesOwnCheers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `/api/v1/training/messages` — the mobile training playlist
 * (docs/renovacion/entrenamientos.md §API). Owner-only (404 for anyone
 * else); audio only as a signed URL that expires in
 * finisher.media.urls.private_ttl_minutes (120 by default).
 */
class TrainingMessageController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;
    use ResolvesOwnCheers;

    public function index(Request $request, ListCheerMessages $list, CheerPresenter $presenter): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $request->validate(['filter' => ['nullable', 'in:'.implode(',', ListCheerMessages::FILTERS)]]);

        $page = $list->paginate($user, (string) ($request->query('filter') ?: 'all'));

        return $this->respond(
            collect($page->items())->map(fn (CheerMessage $m) => $presenter->message($m))->values(),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'unheard_count' => $list->unheardCount($user),
            ],
        );
    }

    public function listened(Request $request, string $uuid, ManageCheerMessages $messages): Response
    {
        $messages->markListened($this->ownMessage($this->sanctumUser($request), $uuid));

        return response()->noContent();
    }

    public function favorite(Request $request, string $uuid, ManageCheerMessages $messages): Response
    {
        $message = $this->ownMessage($this->sanctumUser($request), $uuid);
        $data = $request->validate(['favorite' => ['required', 'boolean']]);

        $messages->setFavorite($message, (bool) $data['favorite']);

        return response()->noContent();
    }

    public function destroy(Request $request, string $uuid, ManageCheerMessages $messages): Response
    {
        $messages->hide($this->ownMessage($this->sanctumUser($request), $uuid));

        return response()->noContent();
    }

    public function blockSender(Request $request, string $uuid, ManageCheerMessages $messages): Response
    {
        $user = $this->sanctumUser($request);
        $messages->blockSender($this->ownMessage($user, $uuid), $user);

        return response()->noContent();
    }
}
