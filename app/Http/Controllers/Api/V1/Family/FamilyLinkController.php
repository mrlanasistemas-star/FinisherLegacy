<?php

namespace App\Http\Controllers\Api\V1\Family;

use App\Actions\Family\InviteFamilyContact;
use App\Actions\Family\RespondToFamilyInvite;
use App\Actions\Family\RevokeFamilyLink;
use App\Enums\FamilyLinkStatus;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingShare;
use App\Support\Family\FamilyPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Familia y contactos autorizados — `/api/v1/family/*` (Sanctum). Contract
 * in docs/renovacion/familia.md. Links someone else owns are 404, never 403,
 * so a uuid never confirms that a link exists.
 */
class FamilyLinkController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * `GET family` — everything the Familia screen needs in one call:
     * my people (`links`), invitations waiting for me, the athletes whose
     * family I am (`memberships`) and how many are sharing with me now.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $links = AthleteFamilyLink::query()
            ->where('athlete_user_id', $user->id)
            ->open()
            ->with('contactUser.athleteProfile')
            ->latest('id')
            ->get();

        $asContact = AthleteFamilyLink::query()
            ->where('contact_user_id', $user->id)
            ->open()
            ->with('athleteUser.athleteProfile')
            ->latest('id')
            ->get();

        $following = TrackingShare::query()
            ->usable()
            ->where('audience', TrackingShare::AUDIENCE_FAMILY)
            ->whereIn('athlete_family_link_id', AthleteFamilyLink::query()->where('contact_user_id', $user->id)->accepted()->select('id'))
            ->count();

        return $this->respond([
            'links' => $links->map(fn (AthleteFamilyLink $l) => FamilyPresenter::forAthlete($l))->values(),
            'invitations' => $asContact->where('status', FamilyLinkStatus::Pending)->map(fn (AthleteFamilyLink $l) => FamilyPresenter::forContact($l))->values(),
            'memberships' => $asContact->where('status', FamilyLinkStatus::Accepted)->map(fn (AthleteFamilyLink $l) => FamilyPresenter::forContact($l))->values(),
            'following_count' => $following,
        ]);
    }

    /**
     * `POST family/links` `{email?, label?}` — with an email, invites that
     * account; without, returns a one-time invite `url` to share.
     */
    public function store(Request $request, InviteFamilyContact $invite): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:191'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $result = $invite->handle($user, $data['email'] ?? null, $data['label'] ?? null);
        $result['link']->load('contactUser.athleteProfile');

        return $this->respond([
            ...FamilyPresenter::forAthlete($result['link']),
            // Shown once: only the hash is stored.
            'invite_url' => $result['url'],
        ], $result['url'] !== null ? __('Enlace de invitación creado.') : __('Invitación enviada.'), status: 201);
    }

    /** `PATCH family/links/{link}` `{label}` — owner renames ("Mamá"). */
    public function update(Request $request, AthleteFamilyLink $link): JsonResponse
    {
        $user = $this->sanctumUser($request);
        abort_unless(Gate::forUser($user)->allows('update', $link) && $link->status->isOpen(), 404);

        $data = $request->validate(['label' => ['nullable', 'string', 'max:60']]);
        $link->update(['label' => isset($data['label']) ? (trim($data['label']) ?: null) : null]);

        return $this->respond(FamilyPresenter::forAthlete($link->load('contactUser.athleteProfile')), __('Guardado.'));
    }

    /**
     * `DELETE family/links/{link}` — the owner removes the person (or
     * cancels a pending invite), or the contact leaves. Their live shares
     * are revoked in the same step.
     */
    public function destroy(Request $request, AthleteFamilyLink $link, RevokeFamilyLink $revoke): Response
    {
        abort_unless(Gate::forUser($this->sanctumUser($request))->allows('delete', $link), 404);

        $revoke->handle($link);

        return response()->noContent();
    }

    /** `POST family/links/{link}/accept` — an email invite addressed to me. */
    public function accept(Request $request, AthleteFamilyLink $link, RespondToFamilyInvite $respond): JsonResponse
    {
        $user = $this->sanctumUser($request);
        abort_unless(Gate::forUser($user)->allows('respond', $link), 404);

        $respond->accept($link, $user);

        return $this->respond(FamilyPresenter::forContact($link->load('athleteUser.athleteProfile')), __('Ahora formas parte de su familia.'));
    }

    /** `POST family/links/{link}/decline` */
    public function decline(Request $request, AthleteFamilyLink $link, RespondToFamilyInvite $respond): JsonResponse
    {
        $user = $this->sanctumUser($request);
        abort_unless(Gate::forUser($user)->allows('respond', $link), 404);

        $respond->decline($link, $user);

        return $this->respond(FamilyPresenter::forContact($link->load('athleteUser.athleteProfile')), __('Invitación rechazada.'));
    }

    /**
     * `GET family/invites/{token}` — preview of a shared invite link (who
     * invites me) before accepting. Used, expired and unknown → 404.
     */
    public function preview(Request $request, string $token): JsonResponse
    {
        $this->sanctumUser($request);
        $link = AthleteFamilyLink::findOpenInvite($token);
        abort_if($link === null, 404, __('Esta invitación ya no está disponible.'));

        return $this->respond([
            ...FamilyPresenter::forContact($link->load('athleteUser.athleteProfile')),
            'is_own' => $link->athlete_user_id === $this->sanctumUser($request)->id,
        ]);
    }

    /** `POST family/invites/{token}/accept` */
    public function acceptToken(Request $request, string $token, RespondToFamilyInvite $respond): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $link = AthleteFamilyLink::findOpenInvite($token);
        abort_if($link === null, 404, __('Esta invitación ya no está disponible.'));

        $respond->accept($link, $user);

        return $this->respond(FamilyPresenter::forContact($link->load('athleteUser.athleteProfile')), __('Ahora formas parte de su familia.'));
    }

    /** `POST family/invites/{token}/decline` */
    public function declineToken(Request $request, string $token, RespondToFamilyInvite $respond): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $link = AthleteFamilyLink::findOpenInvite($token);
        abort_if($link === null, 404, __('Esta invitación ya no está disponible.'));

        $respond->decline($link, $user);

        return $this->respond(null, __('Invitación rechazada.'));
    }
}
