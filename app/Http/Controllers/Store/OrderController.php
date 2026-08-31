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
        $orders = $request->user()->orders()->with('items')->orderByDesc('created_at')->get();

        return Inertia::render('store/Orders', [
            'orders' => $orders->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toDateTimeString(),
            ]),
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
        ]);
    }
}
