<?php

namespace App\Support\Training;

use App\Enums\LegacyTagPurpose;
use App\Models\CartItem;
use App\Models\CheerLink;
use App\Models\LegacyTag;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The store side of the Tarjeta de ánimo NFC (docs/renovacion/tarjeta-animo.md),
 * following the Portarretrato personalization pattern (PortraitDesigns):
 *
 *   cart line metadata   `cheer_link_id`  — the athlete's link the card opens
 *   order item metadata  `cheer_link`     — frozen {id, uuid, title} copy
 *
 * At fulfillment (FulfillOrderItem) the units assigned to the item are bound
 * to that link, so the card arrives ready. Without a link the card ships
 * unbound and the athlete binds it in the app.
 */
final class CheerCards
{
    /** Catalog product created by `php artisan catalog:publish-cheer-card`. */
    public const string PRODUCT_SLUG = 'tarjeta-animo-nfc';

    public const string SKU = 'TARJETA-ANIMO-NFC';

    public static function isCardVariant(ProductVariant $variant): bool
    {
        $variant->loadMissing('product');

        return $variant->product?->slug === self::PRODUCT_SLUG;
    }

    /** Purpose of the units of a lot of this variant. */
    public static function purposeFor(ProductVariant $variant): LegacyTagPurpose
    {
        return self::isCardVariant($variant) ? LegacyTagPurpose::Cheer : LegacyTagPurpose::Story;
    }

    /**
     * Cart metadata for "a card for this link": the link must be the buyer's
     * and the variant the card. [] when no link was sent.
     *
     * @return array{cheer_link_id?: int}
     *
     * @throws ValidationException
     */
    public static function cartMetadata(?User $user, ?CheerLink $link, ProductVariant $variant): array
    {
        if ($link === null) {
            return [];
        }

        if ($user === null || $link->user_id !== $user->id || $link->state() === CheerLink::STATE_REVOKED) {
            throw ValidationException::withMessages(['cheer_link_uuid' => __('No encontramos ese enlace de ánimo.')]);
        }

        if (! self::isCardVariant($variant)) {
            throw ValidationException::withMessages(['cheer_link_uuid' => __('Un enlace de ánimo solo se puede elegir para la Tarjeta de ánimo.')]);
        }

        return ['cheer_link_id' => $link->id];
    }

    /**
     * Frozen copy written on the OrderItem at checkout.
     *
     * @return array{id: int, uuid: string, title: string}|null
     */
    public static function snapshotForCartItem(CartItem $item): ?array
    {
        $id = $item->metadata['cheer_link_id'] ?? null;
        $link = $id !== null ? CheerLink::query()->find((int) $id) : null;

        return $link === null ? null : ['id' => $link->id, 'uuid' => $link->uuid, 'title' => $link->title];
    }

    /**
     * Compact shape on a cart / order line (current title, else the snapshot).
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array{uuid: string, title: string}|null
     */
    public static function summary(?array $metadata): ?array
    {
        $snapshot = $metadata['cheer_link'] ?? null;
        $id = is_array($snapshot) ? ($snapshot['id'] ?? null) : ($metadata['cheer_link_id'] ?? null);

        if ($id === null) {
            return null;
        }

        $link = CheerLink::query()->find((int) $id);

        if ($link === null && ! is_array($snapshot)) {
            return null;
        }

        return [
            'uuid' => (string) ($link->uuid ?? $snapshot['uuid'] ?? ''),
            'title' => (string) ($link->title ?? $snapshot['title'] ?? ''),
        ];
    }

    /** The link an order item asked for, if it still exists and is still the buyer's. */
    public static function linkForOrderItem(OrderItem $item): ?CheerLink
    {
        $snapshot = $item->metadata['cheer_link'] ?? null;
        $id = is_array($snapshot) ? ($snapshot['id'] ?? null) : null;

        if ($id === null) {
            return null;
        }

        $item->loadMissing('order');
        $link = CheerLink::query()->find((int) $id);

        return $link !== null && $item->order !== null && $link->user_id === $item->order->user_id ? $link : null;
    }

    /**
     * Admin order detail: the link the card was bought for and the units
     * assigned to the item with their current binding.
     *
     * @return array{link: array{uuid: string, title: string, state: string|null}|null, cards: list<array{code: string, status: string, bound_to: string|null}>}|null
     */
    public static function forAdmin(OrderItem $item): ?array
    {
        $item->loadMissing('product');

        if ($item->product?->slug !== self::PRODUCT_SLUG && ! isset($item->metadata['cheer_link'])) {
            return null;
        }

        $snapshot = $item->metadata['cheer_link'] ?? null;
        $link = is_array($snapshot) && isset($snapshot['id']) ? CheerLink::query()->find((int) $snapshot['id']) : null;

        $cards = array_values(LegacyTag::query()
            ->where('order_item_id', $item->id)
            ->with('cheerLink:id,title')
            ->get()
            ->map(fn (LegacyTag $tag) => [
                'code' => $tag->displayCode(),
                'status' => $tag->status->label(),
                'bound_to' => $tag->cheerLink?->title,
            ])
            ->all());

        return [
            'link' => is_array($snapshot) ? [
                'uuid' => (string) ($snapshot['uuid'] ?? ''),
                'title' => (string) ($link->title ?? $snapshot['title'] ?? ''),
                'state' => $link?->state(),
            ] : null,
            'cards' => $cards,
        ];
    }

    /** The published card product, when it is for sale. */
    public static function product(): ?Product
    {
        $product = Product::query()->where('slug', self::PRODUCT_SLUG)->with('variants')->first();

        return $product !== null && $product->isPurchasable() ? $product : null;
    }
}
