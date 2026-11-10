<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\ManageCheerLinks;
use App\Actions\Training\ManageCheerMessages;
use App\Actions\Training\ReportCheerMessage;
use App\Enums\CheerReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreCheerLinkRequest;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\User;
use App\Queries\Training\ListCheerMessages;
use App\Support\Training\CheerPresenter;
use App\Support\Training\ResolvesOwnCheers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/mi-entrenamiento` — the athlete's Entrenamientos page: their
 * "Envíame ánimo" links (create, copy, share, revoke) and the playlist of
 * cheers with the web player (listened / favorite / hide / block). Same
 * Actions and Query as /api/v1/training; every lookup is owner-scoped (404
 * for anyone else).
 */
class TrainingController extends Controller
{
    use ResolvesOwnCheers;

    public function index(Request $request, ListCheerMessages $list, CheerPresenter $presenter): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = in_array($request->query('filter'), ListCheerMessages::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $messages = $list->paginate($user, $filter, 30);

        return Inertia::render('training/Index', [
            'filter' => $filter,
            'messages' => [
                'data' => collect($messages->items())->map(fn (CheerMessage $m) => $presenter->message($m))->values(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
            ],
            'counts' => [
                'all' => $list->totalCount($user),
                'unheard' => $list->unheardCount($user),
                'favorites' => $list->favoritesCount($user),
            ],
            'links' => CheerLink::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (CheerLink $link) => $presenter->linkForWeb($link))
                ->values(),
            'expiryOptions' => config('training.cheers.expiry_days', [7, 30, 90]),
        ]);
    }

    public function storeLink(StoreCheerLinkRequest $request, ManageCheerLinks $links): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $links->create($user, (string) $request->validated('title'), $request->expiresInDays());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace listo. Compártelo con tu gente.']);
        Inertia::flash('cheer_link_url', $link->url());

        return back();
    }

    public function destroyLink(Request $request, string $link, ManageCheerLinks $links): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $links->revoke($this->ownLink($user, $link), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace desactivado. Ya no recibe mensajes.']);

        return back();
    }

    public function listened(Request $request, string $message, ManageCheerMessages $messages): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $messages->markListened($this->ownMessage($user, $message));

        return back();
    }

    public function favorite(Request $request, string $message, ManageCheerMessages $messages): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['favorite' => ['required', 'boolean']]);
        $messages->setFavorite($this->ownMessage($user, $message), (bool) $data['favorite']);

        return back();
    }

    public function hide(Request $request, string $message, ManageCheerMessages $messages): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $messages->hide($this->ownMessage($user, $message));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mensaje quitado de tu lista.']);

        return back();
    }

    public function block(Request $request, string $message, ManageCheerMessages $messages): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $messages->blockSender($this->ownMessage($user, $message), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueado. No recibirás más mensajes suyos en ese enlace.']);

        return back();
    }

    public function report(Request $request, string $message, ReportCheerMessage $report): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cheer = $this->ownMessage($user, $message);
        $data = $request->validate(ReportCheerMessage::rules());

        $report->handle($cheer, $user, CheerReportReason::from($data['reason']), $data['details'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Reportamos el mensaje y lo quitamos de tu lista; nuestro equipo lo revisará.']);

        return back();
    }
}
