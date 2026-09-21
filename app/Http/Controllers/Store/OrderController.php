<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mis pedidos" (brief §34/§78-§79) — only the authenticated user's own
 * Orders, never another user's.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $orders->through(fn (Order $order) => [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'items_count' => $order->items_count,
            'created_at' => $order->created_at->toDateTimeString(),
        ]);

        return Inertia::render('store/Orders', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->loadMissing('items');

        return Inertia::render('store/OrderShow', [
            'order' => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'subtotal_minor' => $order->subtotal_minor,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toDateTimeString(),
            ],
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total_minor' => $item->line_total_minor,
                'fulfilled' => $item->isFulfilled(),
            ]),
            'openpay' => $this->openpayFrontendConfig(),
        ]);
    }

    /**
     * Public Openpay.js config only — merchant_id/public_key are meant for
     * client-side use (brief §143: never the private key). Null when the
     * default gateway isn't Openpay or credentials aren't set, so the
     * frontend never tries to load Openpay.js pointlessly.
     *
     * @return array{merchant_id: string, public_key: string, sandbox: bool}|null
     */
    private function openpayFrontendConfig(): ?array
    {
        if (config('finisher.payments.default_gateway') !== 'openpay') {
            return null;
        }

        $merchantId = config('finisher.payments.openpay.merchant_id');
        $publicKey = config('finisher.payments.openpay.public_key');

        if (blank($merchantId) || blank($publicKey)) {
            return null;
        }

        return [
            'merchant_id' => $merchantId,
            'public_key' => $publicKey,
            'sandbox' => ! (bool) config('finisher.payments.openpay.production_mode'),
        ];
    }
}
