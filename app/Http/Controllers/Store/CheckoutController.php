<?php

namespace App\Http\Controllers\Store;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\SyncOnlinePayment;
use App\Enums\PaymentStatus;
use App\Exceptions\Api\ApiException;
use App\Exceptions\PaymentDeclinedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreCheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Queries\Commerce\GetCartSummary;
use App\Support\Commerce\CartSummaryItem;
use App\Support\Commerce\OrderPaymentState;
use App\Support\Commerce\WebCheckoutPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Checkout (spec §25/§28) — one page, four parts: datos, entrega, pago,
 * resumen. Same Actions the API uses, no REST round-trip from Vue.
 *
 *   GET  /checkout                     cart summary + contact/delivery form
 *   POST /checkout                     CheckoutCart → Order (server totals, stock reserved)
 *   GET  /checkout/{order}             same page, payment step (Stripe Payment Element)
 *   POST /checkout/{order}/online-payment   PaymentIntent from the Order's own amount
 *   GET  /checkout/{order}/confirmacion     Stripe's return_url — shows SERVER state only
 *   GET  /checkout/{order}/estado      lightweight status for the confirmation poll
 *
 * Nothing here ever marks an Order paid: the signed webhook does.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, GetOrCreateCart $getOrCreateCart, GetCartSummary $getSummary, WebCheckoutPayment $payments): Response|RedirectResponse
    {
        $user = $request->user();
        $cart = $getOrCreateCart->handle($user, null);
        $summary = $getSummary->handle($cart);

        if ($summary->items->isEmpty()) {
            return redirect()->route('store.cart.show');
        }

        return Inertia::render('store/Checkout', [
            'step' => 'details',
            'items' => $summary->items->map(fn (CartSummaryItem $item) => $item->toPageArray())->values(),
            'subtotal_minor' => $summary->subtotalMinor,
            'discount_minor' => $summary->discountMinor,
            'total_minor' => $summary->totalMinor,
            'currency' => $summary->currency,
            'coupon' => $summary->coupon !== null ? ['code' => $summary->coupon->code] : null,
            'requires_shipping' => $summary->items->contains(fn (CartSummaryItem $item) => $item->requiresShipping),
            'contact' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'payment' => $this->paymentProps($payments),
            'order' => null,
        ]);
    }

    public function store(
        StoreCheckoutRequest $request,
        GetOrCreateCart $getOrCreateCart,
        EnsureAthleteForUser $ensureAthlete,
        CheckoutCart $checkout,
    ): RedirectResponse {
        $user = $request->user();
        $cart = $getOrCreateCart->handle($user, null);
        $athlete = $ensureAthlete->handle($user, 'store_checkout');

        try {
            $order = $checkout->handle($cart, $user, $athlete, $request->customerSnapshot());
        } catch (Throwable $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => $e instanceof ApiException || $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'No pudimos confirmar tu pedido. Intenta de nuevo.']);

            return back();
        }

        return redirect()->route('store.checkout.pay', $order->uuid);
    }

    /**
     * The payment step of the same checkout page, for an Order that
     * already exists (its stock is reserved until it expires).
     */
    public function pay(Request $request, Order $order, WebCheckoutPayment $payments): Response|RedirectResponse
    {
        Gate::authorize('pay', $order);

        $order->loadMissing(['items.product.media', 'items.productVariant', 'latestPayment']);

        if (! OrderPaymentState::isPayable($order)) {
            return redirect()->route('store.checkout.confirmation', $order->uuid);
        }

        $snapshot = $order->customer_snapshot ?? [];

        return Inertia::render('store/Checkout', [
            'step' => 'payment',
            'items' => $order->items->map(fn (OrderItem $item) => $this->orderItemRow($item))->values(),
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'coupon' => $order->coupon_code !== null ? ['code' => $order->coupon_code] : null,
            'requires_shipping' => ($snapshot['delivery']['method'] ?? null) === 'shipping',
            'contact' => [
                'name' => $snapshot['name'] ?? $request->user()->name,
                'email' => $snapshot['email'] ?? $request->user()->email,
                'phone' => $snapshot['phone'] ?? null,
            ],
            'delivery' => $snapshot['delivery'] ?? null,
            'payment' => $this->paymentProps($payments),
            'order' => $this->orderState($order),
        ]);
    }

    /**
     * Stripe's `return_url` and OpenPay's 3D Secure `redirect_url`
     * (OpenPay appends `?id={charge_id}`). The query string is deliberately
     * ignored as a source of truth: for OpenPay the charge is re-read from
     * OpenPay's API (SyncOnlinePayment), and the page polls `estado` until
     * the state is final.
     */
    public function confirmation(Order $order, SyncOnlinePayment $sync): Response
    {
        Gate::authorize('view', $order);

        $this->syncOpenPay($order, $sync);

        $order->refresh()->loadMissing(['items.product.media', 'items.productVariant', 'latestPayment']);

        return Inertia::render('store/CheckoutConfirmation', [
            'order' => $this->orderState($order),
            'items' => $order->items->map(fn (OrderItem $item) => $this->orderItemRow($item))->values(),
            'currency' => $order->currency,
            'total_minor' => $order->total_minor,
            'delivery' => $order->customer_snapshot['delivery'] ?? null,
        ]);
    }

    public function status(Order $order, SyncOnlinePayment $sync): JsonResponse
    {
        Gate::authorize('view', $order);

        // OpenPay has no client-side confirmation: while the attempt is
        // still pending, re-read the charge at most every few seconds so
        // the page doesn't depend on the webhook being configured.
        if (Cache::add("checkout.openpay-sync.{$order->id}", true, now()->addSeconds(8))) {
            $this->syncOpenPay($order, $sync);
        }

        return response()->json(['data' => $this->orderState($order->refresh()->load('latestPayment'))]);
    }

    /**
     * Starts (or resumes) the online payment for an Order — never crashes
     * when the provider has no keys, it reports "not available".
     */
    public function onlinePayment(Request $request, Order $order, CreateOnlinePayment $createPayment, WebCheckoutPayment $payments, SyncOnlinePayment $sync): JsonResponse
    {
        Gate::authorize('pay', $order);

        $provider = $payments->options()['provider'] ?? null;

        if ($provider === null) {
            return response()->json(['available' => false, 'message' => 'El pago en línea no está disponible en este momento.']);
        }

        try {
            $intent = $createPayment->handle(
                $order,
                provider: $provider,
                paymentData: $request->only(['token_id', 'device_session_id']),
            );
        } catch (PaymentDeclinedException $e) {
            return response()->json(['available' => true, 'declined' => true, 'message' => $e->getMessage()]);
        } catch (ApiException $e) {
            return response()->json(['available' => false, 'message' => $e->getMessage()]);
        }

        // An OpenPay card charge without 3DS is final right away — confirm
        // it now (server to server) instead of waiting for the webhook.
        if ($provider === 'openpay' && ($intent->clientPayload['redirect_url'] ?? null) === null) {
            $this->syncOpenPay($order, $sync);
        }

        return response()->json(['available' => true, 'client_payload' => $intent->clientPayload]);
    }

    /**
     * Re-reads the latest pending OpenPay attempt from OpenPay's API and
     * applies it through the same path a webhook takes. Never throws: the
     * webhook (or the next poll) settles it if OpenPay is unreachable.
     */
    private function syncOpenPay(Order $order, SyncOnlinePayment $sync): void
    {
        $latest = $order->latestPayment()->first();

        if ($latest === null
            || $latest->provider->value !== 'openpay'
            || blank($latest->provider_reference)
            || ! in_array($latest->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            return;
        }

        try {
            $sync->handle($order);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentProps(WebCheckoutPayment $payments): array
    {
        return [
            ...$payments->options(),
            'expiry_minutes' => (int) config('finisher.commerce.order_payment_expiry_minutes', 60),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderState(Order $order): array
    {
        $latest = $order->relationLoaded('latestPayment') ? $order->latestPayment : $order->latestPayment()->first();

        return [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'payment_state' => OrderPaymentState::for($order, $latest),
            'payable' => OrderPaymentState::isPayable($order),
            'expires_at' => OrderPaymentState::expiresAt($order)?->toIso8601String(),
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderItemRow(OrderItem $item): array
    {
        $variant = $item->productVariant;

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'product_name' => $item->product->name ?? $item->name,
            'product_slug' => $item->product?->slug,
            'variant_name' => $variant->name ?? '',
            ...($variant?->sizeAndColor() ?? ['size' => null, 'color' => null]),
            'unit_price_minor' => $item->unit_price_minor,
            'line_total_minor' => $item->line_total_minor,
            'currency' => $item->currency,
            'price_available' => true,
            'image_url' => $item->product?->primaryImageUrl(),
            'in_stock' => true,
            'low_stock' => false,
            'max_quantity' => $item->quantity,
            'event_edition_name' => null,
            'connected' => (bool) $item->product?->qr_capable,
            'product_type' => $item->product?->type->value,
            'requires_shipping' => (bool) $item->product?->requires_shipping,
        ];
    }
}
