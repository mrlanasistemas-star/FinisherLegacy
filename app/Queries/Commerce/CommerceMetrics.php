<?php

namespace App\Queries\Commerce;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sales numbers for the Comercio workspace (spec §11 Resumen, Ventas).
 * "Sold" means an Order whose money cleared (paid / partially refunded),
 * counted on the day it was created, in the default currency only — two
 * currencies are never summed together. Every method is a handful of
 * aggregate queries, never a loop over orders.
 */
class CommerceMetrics
{
    public function currency(): string
    {
        return (string) config('finisher.commerce.default_currency', 'MXN');
    }

    /**
     * @return Builder<Order>
     */
    public function paidOrders(): Builder
    {
        return Order::query()
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])
            ->where('currency', $this->currency());
    }

    /**
     * @return array{total_minor: int, orders: int, units: int}
     */
    public function totals(CarbonImmutable $from, ?CarbonImmutable $until = null): array
    {
        $orders = $this->paidOrders()
            ->where('created_at', '>=', $from)
            ->when($until, fn ($q) => $q->where('created_at', '<', $until));

        $row = (clone $orders)->toBase()->selectRaw('coalesce(sum(total_minor), 0) as total_minor, count(*) as orders')->first();

        $units = (int) OrderItem::query()->whereIn('order_id', (clone $orders)->select('id'))->sum('quantity');

        return [
            'total_minor' => (int) ($row->total_minor ?? 0),
            'orders' => (int) ($row->orders ?? 0),
            'units' => $units,
        ];
    }

    /**
     * One point per day (zero-filled).
     *
     * @return list<array{date: string, total_minor: int, orders: int}>
     */
    public function series(CarbonImmutable $from, int $days): array
    {
        $rows = $this->paidOrders()
            ->where('created_at', '>=', $from)
            ->toBase()
            ->groupBy(DB::raw('date(created_at)'))
            ->get([DB::raw('date(created_at) as day'), DB::raw('sum(total_minor) as total_minor'), DB::raw('count(*) as orders')])
            ->keyBy(fn (object $row) => substr((string) $row->day, 0, 10));

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->addDays($i)->toDateString();
            $series[] = [
                'date' => $date,
                'total_minor' => (int) ($rows[$date]->total_minor ?? 0),
                'orders' => (int) ($rows[$date]->orders ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Best sellers by units in the window.
     *
     * @return list<array{product_id: int, name: string, image_url: ?string, units: int, revenue_minor: int}>
     */
    public function topProducts(CarbonImmutable $from, int $limit = 5): array
    {
        $rows = OrderItem::query()
            ->toBase()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->where('orders.currency', $this->currency())
            ->where('orders.created_at', '>=', $from)
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByDesc(DB::raw('sum(order_items.quantity)'))
            ->limit($limit)
            ->get([
                'order_items.product_id',
                DB::raw('sum(order_items.quantity) as units'),
                DB::raw('sum(order_items.line_total_minor) as revenue_minor'),
            ]);

        $products = Product::query()->with('media')->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return array_values($rows->map(fn (object $row) => [
            'product_id' => (int) $row->product_id,
            'name' => (string) ($products->get((int) $row->product_id)->name ?? 'Producto'),
            'image_url' => $products->get((int) $row->product_id)?->primaryImageUrl(),
            'units' => (int) $row->units,
            'revenue_minor' => (int) $row->revenue_minor,
        ])->all());
    }

    /**
     * @return array{awaiting_payment: int, paid: int, to_deliver: int, delivered: int, cancelled: int}
     */
    public function orderCounts(CarbonImmutable $from): array
    {
        $base = fn () => Order::query()->where('created_at', '>=', $from);

        return [
            'awaiting_payment' => Order::query()->where('status', OrderStatus::Pending)->where('payment_status', '!=', OrderPaymentStatus::Paid)->count(),
            'paid' => $base()->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])->count(),
            'to_deliver' => $this->toDeliverQuery()->count(),
            'delivered' => $base()->where('fulfillment_status', FulfillmentStatus::Fulfilled)->count(),
            'cancelled' => $base()->where('status', OrderStatus::Cancelled)->count(),
        ];
    }

    /**
     * Paid, not cancelled, not fully delivered — what the team must hand
     * over (oldest first).
     *
     * @return Builder<Order>
     */
    public function toDeliverQuery(): Builder
    {
        return Order::query()
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Completed])
            ->whereIn('fulfillment_status', [FulfillmentStatus::Unfulfilled, FulfillmentStatus::PartiallyFulfilled])
            ->whereHas('items', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('type', '!=', ProductType::DigitalPhoto->value)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toDeliver(int $limit = 8): array
    {
        return array_values($this->toDeliverQuery()
            ->with(['user:id,first_name,last_name', 'items:id,order_id,name,quantity,fulfilled_at'])
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'customer' => $order->customer_snapshot['name'] ?? $order->user->name ?? 'Cliente',
                'delivery' => $order->customer_snapshot['delivery']['method'] ?? 'pickup',
                'items' => $order->items->whereNull('fulfilled_at')->map(fn (OrderItem $i) => "{$i->quantity}× {$i->name}")->values(),
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPayments(int $limit = 6): array
    {
        return array_values(Payment::query()
            ->with('order:id,uuid,order_number')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $p) => [
                'uuid' => $p->uuid,
                'order_uuid' => $p->order?->uuid,
                'order_number' => $p->order?->order_number,
                'provider' => $p->provider->value,
                'method' => $p->method->value,
                'status' => $p->status->value,
                'amount_minor' => $p->amount_minor,
                'currency' => $p->currency,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Sales by product for the Ventas table (units, revenue, orders).
     *
     * @return list<array<string, mixed>>
     */
    public function byProduct(CarbonImmutable $from, int $limit = 50): array
    {
        $rows = OrderItem::query()
            ->toBase()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->where('orders.currency', $this->currency())
            ->where('orders.created_at', '>=', $from)
            ->groupBy('order_items.product_id')
            ->orderByDesc(DB::raw('sum(order_items.line_total_minor)'))
            ->limit($limit)
            ->get([
                'order_items.product_id',
                DB::raw('max(order_items.name) as fallback_name'),
                DB::raw('sum(order_items.quantity) as units'),
                DB::raw('sum(order_items.line_total_minor) as revenue_minor'),
                DB::raw('count(distinct order_items.order_id) as orders'),
            ]);

        $products = Product::query()->with('media')->whereIn('id', $rows->pluck('product_id')->filter())->get()->keyBy('id');

        return array_values($rows->map(fn (object $row) => [
            'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
            'name' => (string) ($products->get((int) $row->product_id)->name ?? $row->fallback_name),
            'type' => $products->get((int) $row->product_id)?->type->value,
            'image_url' => $products->get((int) $row->product_id)?->primaryImageUrl(),
            'units' => (int) $row->units,
            'revenue_minor' => (int) $row->revenue_minor,
            'orders' => (int) $row->orders,
        ])->all());
    }
}
