<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\SubmitCheerMessage;
use App\Enums\CheerChannel;
use App\Enums\CheerIcon;
use App\Enums\CheerMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreCheerMessageRequest;
use App\Models\CheerLink;
use App\Models\LegacyTag;
use App\Services\Training\CheerQuota;
use App\Support\Commerce\WebCheckoutPayment;
use App\Support\Training\CheerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/animo/{token}` — the public "Envíame ánimo" page (Entrenamientos). No
 * account, no app: family and friends record a voice note in the browser
 * (or upload one), add a short text, name, relationship and an icon.
 * Never indexed (meta robots + X-Robots-Tag). Revoked / expired / full
 * links render the same page in a friendly "unavailable" state; unknown
 * tokens are a 404. Both routes sit behind per-IP / per-link limiters
 * (App\Providers\TrainingServiceProvider).
 *
 * Tarjeta de ánimo NFC + pricing (docs/renovacion/tarjeta-animo.md): a tap
 * on a bound card lands here through `/t/{code}`, which marks the session
 * (rememberCard) so the message counts as `nfc_card` for that card. The page
 * shows what the next message costs (CheerQuota) BEFORE recording; a paid
 * one is stored pending and the sender is sent to the guest OpenPay page
 * (CheerPaymentController). Every visit can leave another message.
 */
class CheerPublicController extends Controller
{
    /** Session key set by `/t/{code}` for a bound card. */
    public const string CARD_SESSION_KEY = 'cheer_card';

    /** A card tap counts for this long (the page may stay open while recording). */
    private const int CARD_SESSION_MINUTES = 120;

    /** Random per-browser key for pack credits (the server only keeps its HMAC). */
    public const string SENDER_COOKIE = 'cheer_sender';

    public function show(Request $request, string $token, CheerPresenter $presenter, CheerQuota $quota, WebCheckoutPayment $payments): Response
    {
        $link = $this->find($token);
        $card = $this->cardFor($request, $link);
        $channel = $card !== null ? CheerChannel::NfcCard : CheerChannel::Link;
        $senderKey = $this->senderKey($request);
        $decision = $quota->evaluate($link, $channel, $card, SubmitCheerMessage::hashSenderKey($senderKey));

        $response = Inertia::render('training/Cheer', [
            'token' => $link->token,
            'title' => $link->title,
            'state' => $link->state(),
            'athlete' => $presenter->athlete($link->user),
            'icons' => CheerIcon::values(),
            'limits' => [
                'max_audio_seconds' => (int) config('training.cheers.max_audio_seconds', 180),
                'max_audio_bytes' => (int) config('training.cheers.max_audio_bytes'),
                'max_body' => (int) config('training.cheers.max_body', 500),
            ],
            'via' => $channel->value,
            'pricing' => [
                ...$decision->toPublic(),
                'payment_available' => $payments->openpayOnly()['provider'] !== null,
            ],
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->setCookie(Cookie::make(self::SENDER_COOKIE, $senderKey, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));

        return $response;
    }

    public function store(StoreCheerMessageRequest $request, string $token, SubmitCheerMessage $submit): RedirectResponse
    {
        $link = $this->find($token);

        if (! $link->isAccepting()) {
            return back()->withErrors(['form' => __('Este enlace ya no recibe mensajes.')]);
        }

        $card = $this->cardFor($request, $link);
        $senderKey = $this->senderKey($request);

        /** @var array{sender_name?: string|null, relationship?: string|null, body?: string|null, icon: string, duration_seconds?: int|string|null, anonymous?: bool|null, email?: string|null, pack?: int|null} $data */
        $data = $request->safe()->only(['sender_name', 'relationship', 'body', 'icon', 'duration_seconds', 'anonymous', 'email', 'pack']);

        $message = $submit->handle($link, $data, $request->file('audio'), $request->ip(), [
            'channel' => $card !== null ? CheerChannel::NfcCard : CheerChannel::Link,
            'card' => $card,
            'sender_key' => $senderKey,
        ]);

        Cookie::queue(Cookie::make(self::SENDER_COOKIE, $senderKey, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));

        // Paid extra message: pay first; it reaches the athlete only once the server confirms.
        if ($message !== null && $message->status === CheerMessageStatus::PendingPayment && $message->order_id !== null) {
            $message->loadMissing('order');
            CheerPaymentController::grant($request, $message->order);

            return redirect()->route('cheer.payment.pay', ['token' => $link->token, 'order' => $message->order->uuid]);
        }

        return back();
    }

    /** Called by `/t/{code}` for a bound Tarjeta de ánimo. */
    public static function rememberCard(Request $request, CheerLink $link, LegacyTag $card): void
    {
        $request->session()->put(self::CARD_SESSION_KEY, [
            'link_id' => $link->id,
            'tag_id' => $card->id,
            'at' => now()->getTimestamp(),
        ]);
    }

    /** The card this visit came from — still bound to this link — or null. */
    private function cardFor(Request $request, CheerLink $link): ?LegacyTag
    {
        $mark = $request->hasSession() ? $request->session()->get(self::CARD_SESSION_KEY) : null;

        if (! is_array($mark) || (int) ($mark['link_id'] ?? 0) !== $link->id
            || (int) ($mark['at'] ?? 0) < now()->subMinutes(self::CARD_SESSION_MINUTES)->getTimestamp()) {
            return null;
        }

        return LegacyTag::query()->whereKey((int) ($mark['tag_id'] ?? 0))->where('cheer_link_id', $link->id)->first();
    }

    private function senderKey(Request $request): string
    {
        $key = $request->cookie(self::SENDER_COOKIE);

        return is_string($key) && preg_match('/^[A-Za-z0-9]{40}$/', $key) === 1 ? $key : Str::random(40);
    }

    private function find(string $token): CheerLink
    {
        return CheerLink::query()->where('token', $token)->with('user.athleteProfile')->firstOrFail();
    }
}
