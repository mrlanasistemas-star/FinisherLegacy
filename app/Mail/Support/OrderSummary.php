<?php

namespace App\Mail\Support;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Turns an Order into the plain, already-formatted data every customer
 * order email renders (items with absolute thumbnail URLs, totals in the
 * order currency, delivery address, payment method). Built from the
 * Order's purchase-time snapshot — never re-reads current catalog prices.
 *
 * Card details: only a brand/last4 the provider handed back and was stored
 * on Payment.metadata (`card_brand`/`card_last4` or `card.brand`/
 * `card.last4`) is ever shown; full card data never reaches Laravel.
 */
final class OrderSummary
{
    /**
     * @return array{
     *     number: string,
     *     customer_name: string|null,
     *     first_name: string|null,
     *     items: list<array{name: string, variant: string|null, quantity: int, total: string, image: string, alt: string}>,
     *     subtotal: string,
     *     discount: string|null,
     *     coupon: string|null,
     *     tax: string|null,
     *     total: string,
     *     currency: string,
     *     delivery_method: string|null,
     *     address: list<string>,
     *     payment_method: string|null,
     *     order_url: string,
     *     needs_activation: bool,
     *     activation_url: string,
     *     activation_label: string,
     *     carrier: string|null,
     *     tracking_number: string|null,
     * }
     */
    public static function for(Order $order): array
    {
        $order->loadMissing(['items.product.media', 'items.productVariant', 'payments']);

        $currency = strtoupper($order->currency ?: 'MXN');

        return [
            'number' => (string) $order->order_number,
            'customer_name' => $name = self::customerName($order),
            'first_name' => $name !== null ? Str::of($name)->trim()->before(' ')->toString() : null,
            'items' => array_values($order->items->map(fn (OrderItem $item) => [
                'name' => (string) $item->name,
                'variant' => $item->productVariant?->name,
                'quantity' => (int) $item->quantity,
                'total' => self::money($item->line_total_minor, $currency),
                'image' => BrandMail::absolute($item->product?->primaryImageUrl()) ?? BrandMail::monogramUrl(),
                'alt' => (string) ($item->product?->conceptImageAlt() ?? $item->name),
            ])->all()),
            'subtotal' => self::money($order->subtotal_minor, $currency),
            'discount' => $order->discount_minor > 0 ? '-'.self::money($order->discount_minor, $currency) : null,
            'coupon' => $order->discount_minor > 0 ? $order->coupon_code : null,
            'tax' => $order->tax_minor > 0 ? self::money($order->tax_minor, $currency) : null,
            'total' => self::money($order->total_minor, $currency),
            'currency' => $currency,
            'delivery_method' => self::deliveryMethod($order),
            'address' => self::address($order),
            'payment_method' => self::paymentMethod($order),
            'order_url' => self::orderUrl($order),
            // Physical Finisher Legacy pieces get activated by the buyer;
            // digital purchases (event photos, photo capacity) do not.
            'needs_activation' => $order->items->contains(
                fn (OrderItem $item) => $item->product !== null && ($item->product->tracks_units || $item->product->qr_capable),
            ),
            'activation_url' => route('activate.index'),
            'activation_label' => self::bareUrl(route('activate.index')),
            'carrier' => filled($order->carrier) ? (string) $order->carrier : null,
            'tracking_number' => filled($order->tracking_number) ? (string) $order->tracking_number : null,
        ];
    }

    /** "https://finisherlegacy.com/activar" → "finisherlegacy.com/activar". */
    private static function bareUrl(string $url): string
    {
        return (string) preg_replace('#^https?://(www\.)?#', '', rtrim($url, '/'));
    }

    public static function money(int $minor, string $currency): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($minor) / 100, 2).' '.strtoupper($currency);
    }

    /** The buyer's order page (account orders), else "Mis pedidos" behind login. */
    public static function orderUrl(Order $order): string
    {
        return $order->user_id !== null
            ? route('store.orders.show', $order->uuid)
            : route('store.orders.index');
    }

    /** Email the buyer gave at checkout, else their account email. */
    public static function recipient(Order $order): ?string
    {
        $email = $order->customer_snapshot['email'] ?? null;

        if (! filled($email)) {
            $order->loadMissing('user');
            $email = $order->user?->email;
        }

        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? (string) $email : null;
    }

    private static function customerName(Order $order): ?string
    {
        $name = $order->customer_snapshot['name'] ?? null;

        if (! filled($name)) {
            $order->loadMissing('user');
            $name = $order->user?->name;
        }

        return filled($name) ? (string) $name : null;
    }

    private static function deliveryMethod(Order $order): ?string
    {
        if ($order->delivery_method !== null) {
            return $order->delivery_method->label();
        }

        $method = DeliveryMethod::tryFrom((string) ($order->customer_snapshot['delivery']['method'] ?? ''));

        return $method?->label();
    }

    /** @return list<string> */
    private static function address(Order $order): array
    {
        $address = $order->shipping_address ?: ($order->customer_snapshot['delivery']['address'] ?? null);

        if (! is_array($address)) {
            return [];
        }

        $line = fn (array $keys): string => collect($keys)
            ->map(fn (string $key) => $address[$key] ?? null)
            ->filter(fn ($value) => is_scalar($value) && filled($value))
            ->implode(', ');

        $known = ['line', 'street', 'neighborhood', 'city', 'state', 'postal_code', 'country'];

        $lines = [
            $line(['line', 'street']),
            $line(['neighborhood']),
            trim($line(['city', 'state']).(filled($address['postal_code'] ?? null) ? ' C.P. '.$address['postal_code'] : '')),
            $line(['country']),
        ];

        // Any other free-form keys an admin may have stored (e.g. references).
        foreach ($address as $key => $value) {
            if (! in_array($key, $known, true) && is_scalar($value) && filled($value)) {
                $lines[] = (string) $value;
            }
        }

        return array_values(array_filter($lines, fn (string $l) => $l !== ''));
    }

    private static function paymentMethod(Order $order): ?string
    {
        /** @var Payment|null $payment */
        $payment = $order->payments
            ->filter(fn (Payment $p) => in_array($p->status, [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true))
            ->sortByDesc('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        $metadata = $payment->metadata ?? [];
        $brand = $metadata['card_brand'] ?? $metadata['card']['brand'] ?? null;
        $last4 = $metadata['card_last4'] ?? $metadata['card']['last4'] ?? null;
        $last4 = is_scalar($last4) ? substr(preg_replace('/\D/', '', (string) $last4) ?? '', -4) : '';

        if ($payment->method === PaymentMethod::OnlineCard && strlen($last4) === 4) {
            $brandLabel = is_string($brand) && $brand !== '' ? ucfirst(strtolower($brand)) : 'Tarjeta';

            return "{$brandLabel} terminación {$last4}";
        }

        return match ($payment->method) {
            PaymentMethod::OnlineCard => 'Tarjeta (pago en línea)',
            PaymentMethod::MercadoPagoTerminal => 'Terminal de pago',
            PaymentMethod::Cash => 'Efectivo',
            PaymentMethod::InAppPurchase => 'Compra dentro de la app',
        };
    }
}
