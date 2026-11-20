<?php

namespace App\Http\Controllers\Training;

use App\Actions\Commerce\CancelOrder;
use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\SyncOnlinePayment;
use App\Actions\Training\CreateCheerMessageOrder;
use App\Actions\Training\PurgeUnpaidCheerMessages;
use App\Http\Controllers\Store\CheckoutController;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\Order;
use App\Services\Commerce\InventoryService;
use App\Support\Commerce\OrderPaymentState;
use App\Support\Commerce\WebCheckoutPayment;
use App\Support\Training\CheerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest payment of an extra cheer message (docs/renovacion/tarjeta-animo.md
 * §Pagos) — the SENDER pays on the web, so no in-app purchase applies.
 *
 *   GET  /animo/{token}/pago/{order}               OpenPay card form (Openpay.js)
 *   POST /animo/{token}/pago/{order}/cargo         token_id + device_session_id → charge
 *   GET  /animo/{token}/pago/{order}/confirmacion  3D Secure return; SERVER state only
 *   GET  /animo/{token}/pago/{order}/estado        confirmation poll
 *   POST /animo/{token}/pago/{order}/cancelar      give up: order cancelled, audio purged
 *
 * Same Actions and rules as the store checkout it extends (amount from the
 * Order, charge re-read from OpenPay, nothing ever marked paid from a
 * redirect). There is no account, so access is a session scope granted
 * when the message was recorded (grant()) — like the app checkout's scoped
 * session — limited to that Order, its link and a short window. The
 * message is delivered by MarkOrderPaid → DeliverPaidCheerMessages.
 */
class CheerPaymentController extends CheckoutController
{
    public const string SESSION_KEY = 'cheer_checkout';

    /** Lets THIS browser pay (and follow) the Order of the message it just recorded. */
    public static function grant(Request $request, Order $order): void
    {
        $scopes = (array) $request->session()->get(self::SESSION_KEY, []);
        $now = now()->getTimestamp();

        // Keep only live scopes, plus this one.
        $scopes = array_filter($scopes, fn ($expires) => (int) $expires > $now);
        $scopes[$order->uuid] = now()->addMinutes(max(30, (int) config('finisher.commerce.order_payment_expiry_minutes', 60) + 30))->getTimestamp();

        $request->session()->put(self::SESSION_KEY, $scopes);
    }

    public function payPage(Request $request, string $token, Order $order, WebCheckoutPayment $payments, CheerPresenter $presenter): Response|RedirectResponse
    {
        [$link, $message] = $this->authorizeScope($request, $token, $order);

        if (! OrderPaymentState::isPayable($order)) {
            return redirect()->route('cheer.payment.confirmation', ['token' => $token, 'order' => $order->uuid]);
        }

        return Inertia::render('training/CheerPayment', $this->pageProps($link, $message, $order, $presenter, 'pay', $payments->openpayOnly()));
    }

    public function charge(Request $request, string $token, Order $order, CreateOnlinePayment $createPayment, WebCheckoutPayment $payments, SyncOnlinePayment $sync): JsonResponse
    {
        $this->authorizeScope($request, $token, $order);

        return $this->chargeOnline(
            $request,
            $order,
            $createPayment,
            $sync,
            $payments->openpayOnly()['provider'],
            route('cheer.payment.confirmation', ['token' => $token, 'order' => $order->uuid]),
        );
    }

    public function confirmationPage(Request $request, string $token, Order $order, SyncOnlinePayment $sync, CheerPresenter $presenter): Response
    {
        [$link, $message] = $this->authorizeScope($request, $token, $order);

        $this->syncOpenPay($order, $sync);

        return Inertia::render('training/CheerPayment', $this->pageProps($link, $message, $order->refresh(), $presenter, 'confirmation', null));
    }

    public function poll(Request $request, string $token, Order $order, SyncOnlinePayment $sync): JsonResponse
    {
        $this->authorizeScope($request, $token, $order);

        // Re-read a pending OpenPay attempt at most every few seconds, so the
        // page never depends on the webhook being configured.
        if (Cache::add("checkout.openpay-sync.{$order->id}", true, now()->addSeconds(8))) {
            $this->syncOpenPay($order, $sync);
        }

        return response()->json(['data' => $this->orderState($order->refresh()->load('latestPayment'))]);
    }

    public function cancel(Request $request, string $token, Order $order, PurgeUnpaidCheerMessages $purge, CancelOrder $cancelOrder, InventoryService $inventory): RedirectResponse
    {
        [, $message] = $this->authorizeScope($request, $token, $order);

        if (! $purge->cancelBySender($message, $cancelOrder, $inventory)) {
            return back()->withErrors(['form' => __('Tu pago se está procesando. Espera unos segundos antes de cancelar.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cancelamos tu mensaje. No se hizo ningún cargo.')]);

        return redirect()->route('cheer.show', $token);
    }

    /**
     * This browser recorded the message, the Order is a cheer Order of THIS
     * link, and the scope has not expired. Anything else is a 404.
     *
     * @return array{0: CheerLink, 1: CheerMessage}
     */
    private function authorizeScope(Request $request, string $token, Order $order): array
    {
        $scopes = (array) $request->session()->get(self::SESSION_KEY, []);
        abort_unless((int) ($scopes[$order->uuid] ?? 0) > now()->getTimestamp(), 404);

        $order->loadMissing('items');
        $item = $order->items->first(fn ($item) => ($item->metadata['kind'] ?? null) === CreateCheerMessageOrder::ITEM_KIND);
        abort_if($item === null || $order->user_id !== null, 404);

        $link = CheerLink::query()->where('token', $token)->with('user.athleteProfile')->firstOrFail();
        $message = CheerMessage::query()->whereKey((int) ($item->metadata['cheer_message_id'] ?? 0))->where('cheer_link_id', $link->id)->first();
        abort_if($message === null, 404);

        return [$link, $message];
    }

    /**
     * @param  array<string, mixed>|null  $payment
     * @return array<string, mixed>
     */
    private function pageProps(CheerLink $link, CheerMessage $message, Order $order, CheerPresenter $presenter, string $step, ?array $payment): array
    {
        $order->loadMissing('items', 'latestPayment');
        $item = $order->items->first();
        $params = ['token' => $link->token, 'order' => $order->uuid];

        return [
            'step' => $step,
            'token' => $link->token,
            'title' => $link->title,
            'athlete' => $presenter->athlete($link->user),
            'line' => [
                'name' => $item?->name,
                'messages' => (int) ($item->metadata['messages'] ?? 1),
                'has_audio' => $message->audio_media_file_id !== null,
                'body' => $message->body !== null ? mb_strimwidth($message->body, 0, 120, '…') : null,
            ],
            'holder_name' => $order->customer_snapshot['name'] ?? null,
            'order' => $this->orderState($order),
            'payment' => $payment,
            'urls' => [
                'online_payment' => route('cheer.payment.charge', $params, false),
                'confirmation' => route('cheer.payment.confirmation', $params, false),
                'status' => route('cheer.payment.status', $params, false),
                'cancel' => route('cheer.payment.cancel', $params, false),
                'retry' => route('cheer.payment.pay', $params, false),
                'back' => route('cheer.show', $link->token, false),
            ],
        ];
    }
}
