<?php

namespace App\Http\Controllers\Admin\Store;

use App\Actions\Commerce\FulfillOrderItem;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Order admin — list + detail (brief §39-§40). Fulfillment goes through
 * App\Actions\Commerce\FulfillOrderItem, the same Action used everywhere
 * else — this controller never mints an AthleteOwnedProduct itself.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with(['user', 'eventEdition.event'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where('order_number', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $orders->through(fn (Order $order) => [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'customer' => $order->user === null ? '—' : $order->user->name,
            'event' => $order->eventEdition?->event?->name,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'created_at' => $order->created_at->toDateTimeString(),
        ]);

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->loadMissing(['items.product', 'items.ownedProduct', 'payments', 'user', 'athlete', 'eventEdition.event']);

        return Inertia::render('admin/orders/Show', [
            'order' => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'customer' => $order->user?->name,
                'athlete' => $order->athlete?->full_name,
                'event' => $order->eventEdition?->event?->name,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'subtotal_minor' => $order->subtotal_minor,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toDateTimeString(),
            ],
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'line_total_minor' => $item->line_total_minor,
                'fulfilled' => $item->isFulfilled(),
                'owned_asset_code' => $item->ownedProduct?->asset_code,
            ]),
            'payments' => $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider->value,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'amount_minor' => $payment->amount_minor,
                'reference' => $payment->provider_reference,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ]),
            'inventoryLocations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => array_map(fn ($case) => $case->value, PaymentMethod::cases()),
        ]);
    }

    public function fulfillItem(Request $request, OrderItem $item, FulfillOrderItem $fulfill): RedirectResponse
    {
        $data = $request->validate([
            'inventory_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
        ]);

        $location = InventoryLocation::query()->whereKey($data['inventory_location_id'])->firstOrFail();
        $fulfill->handle($item, $location);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artículo surtido.']);

        return back();
    }
}
