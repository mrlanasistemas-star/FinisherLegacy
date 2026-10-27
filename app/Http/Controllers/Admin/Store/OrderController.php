<?php

namespace App\Http\Controllers\Admin\Store;

use App\Actions\Commerce\FulfillOrderItem;
use App\Actions\Commerce\ReturnOrderItem;
use App\Enums\DeliveryMethod;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\OrderItem;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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
        $channel = SalesChannel::tryFrom((string) $request->query('canal'));
        $base = fn (): Builder => Order::query()
            ->when($channel, fn ($q) => $q->where('sales_channel', $channel->value))
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

        $board = collect($lanes)->map(function (Closure $scope, string $key) use ($base) {
            // Scopes add where() clauses to the builder in place.
            $query = $base();
            $scope($query);

            return [
                'key' => $key,
                'count' => (clone $query)->count(),
                'total_minor' => (int) (clone $query)->sum('total_minor'),
                'orders' => $query->with(['user', 'eventEdition.event', 'items', 'ally:id,name', 'inventoryLocation:id,name', 'seller:id,first_name,last_name'])
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
                        // Commerce OS: channel / ally / point of sale / seller / delivery.
                        'channel' => $order->sales_channel->value,
                        'ally' => $order->ally?->name,
                        'location' => $order->inventoryLocation?->name,
                        'seller' => $order->seller?->name,
                        'delivery_method' => $order->delivery_method?->value,
                    ])
                    ->all(),
            ];
        })->values();

        return Inertia::render('admin/orders/Index', [
            'board' => $board,
            'filters' => ['q' => $search, 'canal' => $channel?->value],
            'channels' => SalesChannel::options(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->loadMissing(['items.product', 'items.ownedProduct', 'payments', 'user', 'athlete', 'eventEdition.event', 'ally:id,uuid,name', 'inventoryLocation:id,name', 'seller:id,first_name,last_name', 'legacyTags.productVariant:id,sku', 'legacyTags.owner:id,full_name']);

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
                // Contact + delivery the customer gave at checkout (spec §28).
                'contact' => [
                    'name' => $order->customer_snapshot['name'] ?? null,
                    'email' => $order->customer_snapshot['email'] ?? null,
                    'phone' => $order->customer_snapshot['phone'] ?? null,
                ],
                'delivery' => $order->customer_snapshot['delivery'] ?? null,
                // Commerce OS (docs/architecture/commerce-os.md §Pedidos).
                'channel' => $order->sales_channel->value,
                'ally' => $order->ally ? ['uuid' => $order->ally->uuid, 'name' => $order->ally->name] : null,
                'location' => $order->inventoryLocation?->name,
                'seller' => $order->seller?->name,
                'delivery_method' => $order->delivery_method?->value,
                'carrier' => $order->carrier,
                'tracking_number' => $order->tracking_number,
                'shipped_at' => $order->shipped_at?->toDateString(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'notes' => $order->notes,
                'sale_seconds' => $order->sale_started_at !== null ? (int) $order->sale_started_at->diffInSeconds($order->created_at) : null,
            ],
            // Traceability SKU → unit → order → buyer → activation.
            'units' => $order->legacyTags->map(fn (LegacyTag $tag) => [
                'uuid' => $tag->uuid,
                'serial' => $tag->serial,
                'display_code' => $tag->displayCode(),
                'sku' => $tag->productVariant?->sku,
                'order_item_id' => $tag->order_item_id,
                'status' => $tag->status->value,
                'status_label' => $tag->status->label(),
                'owner' => $tag->owner?->full_name,
                'activated_at' => $tag->activated_at?->toIso8601String(),
            ])->values(),
            'deliveryMethods' => DeliveryMethod::options(),
            'canManage' => request()->user()?->can('orders.manage') ?? false,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'line_total_minor' => $item->line_total_minor,
                'fulfilled' => $item->isFulfilled(),
                'owned_asset_code' => $item->ownedProduct?->asset_code,
                'returned_quantity' => (int) ($item->metadata['returned_quantity'] ?? 0),
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

    /**
     * Delivered units that came back → back on hand through
     * App\Actions\Commerce\ReturnOrderItem (inverse movement, audited).
     */
    public function returnItem(Request $request, OrderItem $item, ReturnOrderItem $return): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $return->handle($item, (int) $data['quantity'], $request->user(), $data['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Devolución registrada; el stock volvió al inventario.']);

        return back();
    }
}
