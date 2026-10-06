<?php

namespace App\Http\Controllers\Admin\Store;

use App\Actions\Commerce\FulfillOrderItem;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
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
    /**
     * Kanban board: one lane per real stage of the order lifecycle (derived
     * from status / payment_status / fulfillment_status — no new state).
     * Each lane shows its total count and its newest cards.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $base = fn () => Order::query()
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))));

        $lanes = [
            'to_pay' => fn ($q) => $q->where('status', OrderStatus::Pending)->where('payment_status', '!=', OrderPaymentStatus::Paid),
            'to_prepare' => fn ($q) => $q->where('status', OrderStatus::Confirmed)->where('fulfillment_status', FulfillmentStatus::Unfulfilled),
            'in_progress' => fn ($q) => $q->where('status', OrderStatus::Confirmed)->where('fulfillment_status', FulfillmentStatus::PartiallyFulfilled),
            'done' => fn ($q) => $q->where(fn ($w) => $w->where('status', OrderStatus::Completed)
                ->orWhere(fn ($c) => $c->where('status', OrderStatus::Confirmed)->where('fulfillment_status', FulfillmentStatus::Fulfilled))),
            'cancelled' => fn ($q) => $q->where('status', OrderStatus::Cancelled),
        ];

        $board = collect($lanes)->map(function (callable $scope, string $key) use ($base) {
            $query = $scope($base());

            return [
                'key' => $key,
                'count' => (clone $query)->count(),
                'total_minor' => (int) (clone $query)->sum('total_minor'),
                'orders' => $query->with(['user', 'eventEdition.event', 'items'])
                    ->latest('id')
                    ->limit(30)
                    ->get()
                    ->map(fn (Order $order) => [
                        'id' => $order->id,
                        'uuid' => $order->uuid,
                        'order_number' => $order->order_number,
                        'customer' => $order->user?->name,
                        'event' => $order->eventEdition?->event?->name,
                        'total_minor' => $order->total_minor,
                        'currency' => $order->currency,
                        'payment_status' => $order->payment_status->value,
                        'fulfillment_status' => $order->fulfillment_status->value,
                        'items_count' => (int) $order->items->sum('quantity'),
                        'items_preview' => $order->items->take(2)->pluck('name')->values(),
                        'has_photos' => $order->items->contains(fn (OrderItem $i) => $i->event_photo_id !== null),
                        'created_at' => $order->created_at->toIso8601String(),
                    ]),
            ];
        })->values();

        return Inertia::render('admin/orders/Index', [
            'board' => $board,
            'filters' => ['q' => $search],
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
