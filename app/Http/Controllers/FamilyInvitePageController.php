<?php

namespace App\Http\Controllers;

use App\Actions\Family\RespondToFamilyInvite;
use App\Models\AthleteFamilyLink;
use App\Support\Family\FamilyPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/familia/invitacion/{token}` — what someone opens from a shared family
 * invite link (docs/renovacion/familia.md). Public preview (who invites
 * you, nothing else); accepting or declining requires being signed in —
 * guests go through `/entrar?volver=…` and come back here. With the app
 * installed the same https link opens the app's invite screen instead.
 */
class FamilyInvitePageController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $link = AthleteFamilyLink::findOpenInvite($token);

        if ($link === null) {
            return Inertia::render('family/Invite', ['available' => false, 'invite' => null, 'token' => null])
                ->toResponse($request)->setStatusCode(404);
        }

        $link->load('athleteUser.athleteProfile');
        $user = $request->user();

        return Inertia::render('family/Invite', [
            'available' => true,
            'token' => $token,
            'invite' => [
                'athlete' => FamilyPresenter::person($link->athleteUser),
                'label' => $link->label,
                'expires_at' => $link->invite_expires_at?->toIso8601String(),
                'is_own' => $user !== null && $user->id === $link->athlete_user_id,
            ],
            'appUrl' => sprintf('%s://family/invite/%s', config('nfc.app.scheme', 'finisherlegacy'), $token),
        ])->toResponse($request);
    }

    public function accept(Request $request, string $token, RespondToFamilyInvite $respond): RedirectResponse
    {
        return $this->answer($token, fn (AthleteFamilyLink $link) => $respond->accept($link, $request->user()), 'accepted', __('Ahora formas parte de su familia.'));
    }

    public function decline(Request $request, string $token, RespondToFamilyInvite $respond): RedirectResponse
    {
        return $this->answer($token, fn (AthleteFamilyLink $link) => $respond->decline($link, $request->user()), 'declined', __('Invitación rechazada.'));
    }

    /**
     * `/familia/invitacion/listo` — after answering (the token is used up,
     * so the invite URL itself is gone). `?r=accepted|declined`.
     */
    public function done(Request $request): Response
    {
        $result = $request->query('r') === 'declined' ? 'declined' : 'accepted';

        return Inertia::render('family/Invite', ['available' => false, 'invite' => null, 'token' => null, 'result' => $result])
            ->toResponse($request);
    }

    /** @param  callable(AthleteFamilyLink): mixed  $action */
    private function answer(string $token, callable $action, string $result, string $message): RedirectResponse
    {
        $link = AthleteFamilyLink::findOpenInvite($token);

        if ($link === null) {
            return back()->withErrors(['invite' => __('Esta invitación ya no está disponible.')]);
        }

        try {
            $action($link);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('family.invite.done', ['r' => $result]);
    }
}
