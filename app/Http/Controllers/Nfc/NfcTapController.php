<?php

namespace App\Http\Controllers\Nfc;

use App\Actions\Nfc\ClaimNfcTag;
use App\Actions\Nfc\RecordNfcTap;
use App\Enums\NfcLinkStatus;
use App\Enums\NfcTargetType;
use App\Exceptions\Athletes\AthleteIdentityConflictException;
use App\Exceptions\Nfc\NfcAlreadyClaimedException;
use App\Exceptions\Nfc\NfcNotClaimableException;
use App\Http\Controllers\Controller;
use App\Models\LegacyTag;
use App\Models\NfcTag;
use App\Queries\Nfc\ResolveNfcTag;
use App\Support\Nfc\NfcToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /n/{token}` — what every NFC chip opens (docs/NFC.md §Tap):
 * connected → straight to its story/product/profile/event; not yet
 * connected → the activation card (login/register → claim → Mi equipo);
 * disabled → an honest page. If the app is installed, the OS opens it via
 * universal/app links before this ever runs.
 */
class NfcTapController extends Controller
{
    public function show(Request $request, string $token, ResolveNfcTag $resolve, RecordNfcTap $recordTap): Response|RedirectResponse
    {
        $tag = $this->findTag($token);
        $recordTap->handle($tag, $request, 'web', $request->user()?->id);

        // A chip that belongs to a Finisher Legacy Tag opens its /t/{code}
        // flow (docs/architecture/commerce-os.md §Activación y Mi Legacy).
        $legacyTagCode = LegacyTag::query()->where('nfc_tag_id', $tag->id)->value('public_code');
        if (is_string($legacyTagCode)) {
            return redirect()->route('tag.open', $legacyTagCode);
        }

        $resolution = $resolve->handle($tag, $request->user());

        if ($resolution->status === NfcLinkStatus::Claimed && $resolution->redirectUrl !== null) {
            return redirect()->to($resolution->redirectUrl);
        }

        return Inertia::render('nfc/Tap', [
            'token' => $tag->token,
            'status' => $resolution->status->value,
            'reason' => $resolution->reason,
            'canClaim' => $resolution->canClaim,
            'target' => $resolution->target,
            'targetLabel' => $resolution->targetType?->label(),
            'appLink' => $this->appLink($tag->token),
        ]);
    }

    /**
     * Guest → login/register remembering this tag, so Fortify's
     * redirect()->intended() brings them straight back to finish.
     */
    public function continueTo(Request $request, string $token, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['login', 'register'], true), 404);
        $tag = $this->findTag($token);

        $request->session()->put('url.intended', route('nfc.tap', $tag->token));

        return redirect()->route($provider);
    }

    public function claim(Request $request, string $token, ClaimNfcTag $claim): RedirectResponse
    {
        $tag = $this->findTag($token);

        try {
            $assignment = $claim->handle($tag, $request->user(), $request->ip());
        } catch (NfcAlreadyClaimedException|NfcNotClaimableException|AthleteIdentityConflictException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo. Ya forma parte de tu equipo.']);

        return $assignment->target_type === NfcTargetType::Profile
            ? redirect()->route('dashboard.profile.show')
            : redirect()->route('dashboard.my-gear');
    }

    private function findTag(string $token): NfcTag
    {
        abort_unless(NfcToken::isWellFormed($token), 404);

        return NfcTag::query()->where('token', $token)->firstOrFail();
    }

    /**
     * Deep link hint for the activation page (docs/NFC.md §Deep links) —
     * the smart app banner only renders when the iOS app id is configured.
     *
     * @return array{scheme_url: string, ios_app_id: ?string}
     */
    private function appLink(string $token): array
    {
        return [
            'scheme_url' => config('nfc.app.scheme', 'finisherlegacy').'://nfc/'.$token,
            'ios_app_id' => config('nfc.app.ios_app_id') ? (string) config('nfc.app.ios_app_id') : null,
        ];
    }
}
