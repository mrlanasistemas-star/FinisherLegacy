<?php

namespace App\Actions\LegacyTags;

use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\DeliveryMethod;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\LegacyTags\TagLifecycle;
use App\Support\CodeGenerator;
use App\Support\LegacyTags\TagCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "Cobro + entrega inmediata" (docs/architecture/commerce-os.md §Venta rápida):
 * the 90-second in-person sale at a stand, an event or an ally's counter.
 *
 * One transaction: the scanned (or first available) units at that location
 * are locked, an Order is created already paid/fulfilled/completed with its
 * channel, ally, location and seller, a manual Payment records cash or
 * terminal, stock leaves through InventoryService::sellDirect, and every
 * unit becomes `delivered` — ready for its buyer to activate. No
 * personalisation, no production queue.
 */
class RecordInPersonSale
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly TagLifecycle $lifecycle,
        private readonly ResolveProductPrice $prices,
    ) {}

    /**
     * @param  list<string>  $scannedCodes  codes/URLs read from the tags handed over (preferred)
     * @param  array<int, int>  $quantities  variant id => quantity, picked FIFO when nothing is scanned
     * @param  array{name?: ?string, email?: ?string, phone?: ?string}  $customer
     */
    public function handle(
        InventoryLocation $location,
        User $seller,
        PaymentMethod $method,
        array $scannedCodes = [],
        array $quantities = [],
        array $customer = [],
        ?string $paymentReference = null,
        ?CarbonInterface $startedAt = null,
    ): Order {
        if ($method === PaymentMethod::OnlineCard) {
            throw new InvalidArgumentException('La venta en persona se cobra en efectivo o terminal.');
        }

        $channel = self::channelFor($location);

        return DB::transaction(function () use ($location, $seller, $method, $scannedCodes, $quantities, $customer, $paymentReference, $startedAt, $channel) {
            $tags = $this->lockUnits($location, $scannedCodes, $quantities);
            $tags->loadMissing('productVariant.product');

            $lines = $tags->groupBy('product_variant_id')->map(function (\Illuminate\Support\Collection $units) use ($channel, $location) {
                /** @var ProductVariant $variant */
                $variant = $units->first()->productVariant;
                $price = $this->prices->handle($variant->product, $variant, $location->eventEdition, null, $channel, $location->ally_id);

                return ['variant' => $variant, 'units' => $units, 'unit_price' => $price->amountMinor, 'currency' => $price->currency];
            })->values();

            $subtotal = $lines->sum(fn (array $l) => $l['unit_price'] * $l['units']->count());
            $customer = array_filter($customer, fn ($v) => filled($v));

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
                'event_edition_id' => $location->event_edition_id,
                'status' => OrderStatus::Completed,
                'payment_status' => OrderPaymentStatus::Paid,
                'fulfillment_status' => FulfillmentStatus::Fulfilled,
                'sales_channel' => $channel,
                'ally_id' => $location->ally_id,
                'inventory_location_id' => $location->id,
                'seller_user_id' => $seller->id,
                'delivery_method' => DeliveryMethod::InPerson,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $subtotal,
                'currency' => $lines->first()['currency'] ?? 'MXN',
                'customer_snapshot' => $customer === [] ? null : $customer,
                'sale_started_at' => $startedAt,
                'confirmed_at' => now(),
                'completed_at' => now(),
                'delivered_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];
                $count = $line['units']->count();

                $item = OrderItem::create([
                    'uuid' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'name' => $variant->product->name,
                    'sku' => $variant->sku,
                    'quantity' => $count,
                    'unit_price_minor' => $line['unit_price'],
                    'line_total_minor' => $line['unit_price'] * $count,
                    'currency' => $line['currency'],
                    'fulfilled_at' => now(),
                ]);

                $this->inventory->sellDirect($variant, $location, $count, OrderItem::class, $item->id, $seller);

                foreach ($line['units'] as $tag) {
                    $this->lifecycle->transition($tag, LegacyTagEventType::Sold, LegacyTagStatus::Delivered, [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'sales_channel' => $channel,
                        'ally_id' => $location->ally_id,
                        'sold_at' => now(),
                        'delivered_at' => now(),
                    ], $seller, null, ['payment' => $method->value]);
                }
            }

            Payment::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'provider' => PaymentProvider::Manual,
                'method' => $method,
                'status' => PaymentStatus::Paid,
                'amount_minor' => $subtotal,
                'currency' => $order->currency,
                'provider_reference' => $paymentReference,
                'paid_at' => now(),
                'created_by' => $seller->id,
            ]);

            activity()->causedBy($seller)->performedOn($order)
                ->withProperties(['channel' => $channel->value, 'location' => $location->slug, 'units' => $tags->count(), 'total_minor' => $subtotal])
                ->log('pos.sale.recorded');

            return $order->fresh(['items', 'legacyTags']);
        });
    }

    public static function channelFor(InventoryLocation $location): SalesChannel
    {
        return match ($location->type) {
            InventoryLocationType::Ally => SalesChannel::Ally,
            InventoryLocationType::Event, InventoryLocationType::Stand => SalesChannel::OwnEvent,
            default => SalesChannel::Direct,
        };
    }

    /**
     * @param  list<string>  $scannedCodes
     * @param  array<int, int>  $quantities
     * @return Collection<int, LegacyTag>
     */
    private function lockUnits(InventoryLocation $location, array $scannedCodes, array $quantities): Collection
    {
        $codes = collect($scannedCodes)->map(fn (string $c) => TagCode::normalize($c))->filter()->unique()->values();

        if ($codes->count() !== count(array_filter($scannedCodes, 'filled'))) {
            throw new InvalidArgumentException('Hay un código que no corresponde a un Finisher Legacy Tag.');
        }

        $tags = new Collection;

        if ($codes->isNotEmpty()) {
            $tags = LegacyTag::query()->whereIn('public_code', $codes->all())->lockForUpdate()->get();

            foreach ($codes as $code) {
                /** @var LegacyTag|null $tag */
                $tag = $tags->firstWhere('public_code', $code);

                if ($tag === null) {
                    throw new InvalidArgumentException('Código '.TagCode::format($code).' no encontrado.');
                }

                if ($tag->status !== LegacyTagStatus::InStock || $tag->inventory_location_id !== $location->id) {
                    throw new InvalidArgumentException("La pieza {$tag->displayCode()} no está disponible en {$location->name} ({$tag->status->label()}).");
                }
            }
        }

        foreach ($quantities as $variantId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity < 1) {
                continue;
            }

            $picked = LegacyTag::query()
                ->where('product_variant_id', $variantId)
                ->where('inventory_location_id', $location->id)
                ->where('status', LegacyTagStatus::InStock->value)
                ->whereNotIn('id', $tags->pluck('id'))
                ->orderBy('received_at')->orderBy('id')
                ->limit($quantity)
                ->lockForUpdate()->get();

            if ($picked->count() < $quantity) {
                throw new InvalidArgumentException("Solo hay {$picked->count()} piezas disponibles de ese diseño en {$location->name}.");
            }

            $tags = $tags->concat($picked);
        }

        if ($tags->isEmpty()) {
            throw new InvalidArgumentException('Agrega al menos una pieza a la venta.');
        }

        return $tags->values();
    }
}
