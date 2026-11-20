<?php

namespace App\Actions\Training;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\CheerMessage;
use App\Models\Order;
use App\Models\Product;
use App\Support\CodeGenerator;
use App\Support\Training\CheerQuotaDecision;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns "this extra cheer must be paid" into a regular pending guest Order
 * (no account: `user_id` null) with one OrderItem on the internal
 * `mensaje-de-animo` product — the same pattern as CheckoutPhotoExpansion.
 * The sender then pays it with the existing OpenPay web checkout
 * (CheerPaymentController); the message is delivered ONLY when the server
 * confirms the payment (MarkOrderPaid → DeliverPaidCheerMessages).
 *
 * Everything that decides the charge is resolved here, server-side, and
 * frozen on the item: price (or pack), step, channel, link, card and the
 * sender's credit key. Runs inside SubmitCheerMessage's transaction.
 */
class CreateCheerMessageOrder
{
    public const string ITEM_KIND = 'cheer_message';

    public const string PRODUCT_SLUG = 'mensaje-de-animo';

    /**
     * @param  int|null  $packMessages  a pack size offered in $decision->packs, or null for one message
     * @param  array{name?: string|null, email?: string|null}  $contact
     *
     * @throws ValidationException
     */
    public function handle(CheerMessage $message, CheerQuotaDecision $decision, ?int $packMessages, array $contact): Order
    {
        $pack = null;

        if ($packMessages !== null && $packMessages > 1) {
            $pack = collect($decision->packs)->firstWhere('messages', $packMessages);

            if ($pack === null) {
                throw ValidationException::withMessages(['pack' => __('Ese paquete ya no está disponible. Recarga la página.')]);
            }
        }

        $amount = $pack !== null ? (int) $pack['amount_minor'] : (int) $decision->priceMinor;
        $currency = $decision->setting->currency;
        $product = Product::query()->where('slug', self::PRODUCT_SLUG)->firstOrFail();
        $name = trim((string) ($contact['name'] ?? ''));
        $email = trim((string) ($contact['email'] ?? ''));

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
            'user_id' => null,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal_minor' => $amount,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => $amount,
            'currency' => $currency,
            // Only what OpenPay's anti-fraud may use; never the IP.
            'customer_snapshot' => array_filter([
                'locale' => app()->getLocale(),
                'name' => $name !== '' ? $name : __('Anónimo'),
                'email' => $email !== '' ? $email : null,
                'guest' => true,
            ], fn ($v) => $v !== null),
        ]);

        $messages = $pack !== null ? (int) $pack['messages'] : 1;

        $order->items()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => $messages > 1
                ? __('Paquete de :count mensajes de ánimo', ['count' => $messages])
                : __('Mensaje de ánimo'),
            'sku' => $messages > 1 ? 'CHEER-PACK-'.$messages : 'CHEER-MSG',
            'quantity' => 1,
            'unit_price_minor' => $amount,
            'line_total_minor' => $amount,
            'currency' => $currency,
            'metadata' => [
                'kind' => self::ITEM_KIND,
                'cheer_message_id' => $message->id,
                'cheer_message_uuid' => $message->uuid,
                'cheer_link_id' => $message->cheer_link_id,
                'legacy_tag_id' => $message->legacy_tag_id,
                'channel' => $message->source->value,
                'messages' => $messages,
                'paid_count_before' => $decision->paidCount,
                'sender_key_hash' => $message->sender_key_hash,
                'tax_included' => true,
                'tax_rate' => 0,
            ],
        ]);

        $message->forceFill(['order_id' => $order->id, 'amount_minor' => $amount, 'currency' => $currency])->save();

        return $order;
    }
}
