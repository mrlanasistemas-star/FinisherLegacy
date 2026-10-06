<?php

namespace App\Actions\Photos;

use App\Enums\EventPhotoStatus;
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
        $photos = EventPhoto::query()
            ->whereIn('uuid', array_values(array_unique($photoUuids)))
            ->where('status', EventPhotoStatus::Published)
            ->with('eventEdition.event')
            ->get();

        if ($photos->isEmpty()) {
            throw ValidationException::withMessages(['photos' => 'Selecciona al menos una fotografía disponible.']);
        }

        $alreadyOwned = PhotoSale::query()
            ->where('buyer_user_id', $buyer->id)
            ->whereIn('event_photo_id', $photos->pluck('id'))
            ->pluck('event_photo_id')
            ->all();

        $photos = $photos->reject(fn (EventPhoto $photo) => in_array($photo->id, $alreadyOwned, true))->values();

        if ($photos->isEmpty()) {
            throw ValidationException::withMessages(['photos' => 'Ya compraste estas fotografías: están en Mis fotos.']);
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
