<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\RecordTagScan;
use App\Enums\LegacyTagStatus;
use App\Enums\TagScanEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LegacyTags\Concerns\FindsLegacyTag;
use App\Http\Controllers\Training\CheerPublicController;
use App\Models\LegacyTag;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\TagActivationState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /t/{code}` — what the NFC chip opens (old `?src=qr` links from the
 * retired printed QR still work and are counted as such) (docs/architecture/commerce-os.md §Activación y Mi Legacy):
 *
 *   activated   → the story (owner always; others per its visibility,
 *                 else a friendly "Este recuerdo es privado")
 *   sold/delivered (or unregistered field sale) → /activar/{code}
 *   disabled/replaced/returned/warehouse stock → an honest page
 *
 * A Tarjeta de ánimo NFC (docs/renovacion/tarjeta-animo.md) never opens a
 * story: bound to a cheer link it sends the visitor to `/animo/{token}`
 * (no account, no app) and marks the session so that message counts as
 * `nfc_card`; unbound / deactivated it shows a friendly inactive page.
 */
class TagOpenController extends Controller
{
    use FindsLegacyTag;

    public function __invoke(Request $request, string $code, RecordTagScan $scans, StoryPresenter $presenter): Response|RedirectResponse
    {
        $tag = $this->findTagOrFail($code);
        $user = $request->user();
        $source = RecordTagScan::sourceFrom($request);
        $state = TagActivationState::for($tag, $user);

        $scans->handle($tag, TagScanEvent::TagOpened, $source, $user, $request, $state->state === TagActivationState::MINE);

        if ($tag->isCheerCard()) {
            return $this->openCheerCard($request, $tag, $state, $presenter);
        }

        if ($state->state === TagActivationState::MINE) {
            return $tag->story !== null
                ? redirect()->route('legacy.story.show', $tag->story->public_id)->with('legacy.via', $source->value)
                : redirect()->route('activate.show', $tag->public_code);
        }

        if ($state->state === TagActivationState::TAKEN) {
            $story = $tag->story;

            if ($story !== null && Gate::forUser($user)->allows('view', $story)) {
                return redirect()->route('legacy.story.show', $story->public_id)->with('legacy.via', $source->value);
            }

            return Inertia::render('legacy/Private', [
                'tag' => $presenter->tag($tag),
                'loginUrl' => route('activate.continue', ['code' => $tag->public_code, 'provider' => 'login']),
            ]);
        }

        if ($state->canActivate()) {
            return redirect()->route('activate.show', ['code' => $tag->public_code, 'src' => $source->value]);
        }

        return Inertia::render('activate/Unavailable', [
            'reason' => $state->reason,
            'message' => $state->message(),
            'tag' => $presenter->tag($tag),
        ]);
    }

    private function openCheerCard(Request $request, LegacyTag $tag, TagActivationState $state, StoryPresenter $presenter): Response|RedirectResponse
    {
        $link = $tag->status === LegacyTagStatus::Activated && $tag->cheer_link_id !== null ? $tag->cheerLink : null;

        if ($link !== null) {
            CheerPublicController::rememberCard($request, $link, $tag);

            return redirect()->route('cheer.show', $link->token);
        }

        // Disabled, lost, stolen, replaced… keep the existing honest page.
        if ($state->state === TagActivationState::UNAVAILABLE && $state->reason !== 'not_sold') {
            return Inertia::render('activate/Unavailable', [
                'reason' => $state->reason,
                'message' => $state->message(),
                'tag' => $presenter->tag($tag),
            ]);
        }

        return Inertia::render('activate/Unavailable', [
            'reason' => 'cheer_inactive',
            'message' => __('Esta Tarjeta de ánimo no está activa en este momento. Si es tuya, actívala o vincúlala a un enlace desde la app Finisher Legacy.'),
            'tag' => null,
        ]);
    }
}
