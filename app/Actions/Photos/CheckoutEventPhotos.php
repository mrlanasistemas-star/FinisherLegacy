<?php

namespace App\Actions\Photos;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\EventPhoto;
use App\Models\Order;
use App\Models\PhotoSale;
use App\Models\Product;
use App\Models\User;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a selection of published event photos into a regular pending
 * Order (one OrderItem per photo, pointing at the internal "Fotografía
 * digital" product + `event_photo_id`). Payment then goes through the
 * existing order payment flow (CreateOnlinePayment / Stripe); once paid,
 * RecordPhotoSales writes the split and unlocks the downloads.
 */
class CheckoutEventPhotos
{
    /**
     * @param  list<string>  $photoUuids
     */
    public function handle(User $buyer, array $photoUuids): Order
    {
        // Every requested uuid is accounted for: all must be buyable now,
        // or nothing is charged and the buyer is told which ones changed
        // (deleted, unpublished, photographer suspended, already bought).
        // Never a silent partial order.
        $requested = array_values(array_unique(array_filter($photoUuids, 'is_string')));

        if ($requested === []) {
            throw ValidationException::withMessages(['photos' => 'Selecciona al menos una fotografía disponible.']);
        }

        $validUuids = array_values(array_filter($requested, fn (string $uuid) => Str::isUuid($uuid)));

        $photos = EventPhoto::query()
            ->whereIn('uuid', $validUuids)
            ->forSale()
            ->with('eventEdition.event')
            ->get();

        $unavailable = array_values(array_diff($requested, $photos->pluck('uuid')->all()));

        $owned = PhotoSale::query()
            ->where('buyer_user_id', $buyer->id)
            ->whereIn('event_photo_id', $photos->pluck('id'))
            ->pluck('event_photo_id')
            ->all();
        $ownedUuids = $photos->filter(fn (EventPhoto $photo) => in_array($photo->id, $owned, true))->pluck('uuid')->all();

        // Already inside one of this buyer's orders still waiting for
        // payment: point them to it instead of creating a second charge.
        $pendingOrder = Order::query()
            ->where('user_id', $buyer->id)
            ->where('status', OrderStatus::Pending)
            ->where('payment_status', OrderPaymentStatus::Pending)
            ->whereHas('items', fn ($q) => $q->whereIn('event_photo_id', $photos->pluck('id')))
            ->with('items:id,order_id,event_photo_id')
            ->latest('id')
            ->first();
        $pendingUuids = $pendingOrder === null ? [] : $photos
            ->filter(fn (EventPhoto $photo) => $pendingOrder->items->contains('event_photo_id', $photo->id))
            ->pluck('uuid')->values()->all();

        if ($pendingUuids !== []) {
            throw ValidationException::withMessages([
                'photos' => (count($pendingUuids) === 1 ? '1 fotografía ya está' : count($pendingUuids).' fotografías ya están')
                    ." en tu pedido {$pendingOrder->order_number}, pendiente de pago. Complétalo desde Mis pedidos — no se cobró nada.",
                'pending_order' => $pendingOrder->uuid,
            ]);
        }

        if ($unavailable !== [] || $ownedUuids !== []) {
            $messages = [];

            if ($unavailable !== []) {
                $messages[] = count($unavailable) === 1
                    ? '1 fotografía de tu selección ya no está disponible.'
                    : count($unavailable).' fotografías de tu selección ya no están disponibles.';
            }

            if ($ownedUuids !== []) {
                $messages[] = count($ownedUuids) === 1
                    ? '1 fotografía ya la compraste: está en Mis fotos.'
                    : count($ownedUuids).' fotografías ya las compraste: están en Mis fotos.';
            }

            throw ValidationException::withMessages([
                'photos' => implode(' ', $messages).' Las quitamos de tu selección; revisa y confirma de nuevo — no se cobró nada.',
                // Machine-readable so the page can deselect exactly those.
                'photos_removed' => implode(',', [...$unavailable, ...$ownedUuids]),
            ]);
        }

        if ($photos->pluck('currency')->unique()->count() > 1) {
            throw ValidationException::withMessages(['photos' => 'Las fotografías deben tener la misma moneda.']);
        }

        $product = Product::query()->where('slug', 'fotografia-digital')->firstOrFail();
        $subtotal = (int) $photos->sum('price_minor');

        return DB::transaction(function () use ($buyer, $photos, $product, $subtotal) {
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
                'user_id' => $buyer->id,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Pending,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $subtotal,
                'currency' => $photos->first()->currency,
                'customer_snapshot' => ['name' => $buyer->name, 'email' => $buyer->email],
            ]);

            foreach ($photos as $photo) {
                $order->items()->create([
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'event_photo_id' => $photo->id,
                    'name' => 'Fotografía · '.($photo->eventEdition?->event?->name ?? 'Evento'),
                    'sku' => 'PHOTO-'.Str::upper(Str::substr($photo->uuid, 0, 8)),
                    'quantity' => 1,
                    'unit_price_minor' => $photo->price_minor,
                    'line_total_minor' => $photo->price_minor,
                    'currency' => $photo->currency,
                    'metadata' => ['event_photo_uuid' => $photo->uuid, 'photographer_profile_id' => $photo->photographer_profile_id],
                ]);
            }

            return $order;
        });
    }
}
