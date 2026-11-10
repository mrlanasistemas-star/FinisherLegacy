<?php

namespace App\Services\Iap;

use App\Models\LegacyStory;
use App\Models\PhotoExpansionPack;
use App\Models\User;
use App\Services\Media\StoryPhotoQuota;

/**
 * What the app may buy with store billing, and the glue between a store
 * product id and a PhotoExpansionPack (docs/renovacion/compras-dentro-de-app.md).
 *
 * - The pack offered for a story is the most specific sellable pack that
 *   covers it AND has a product id for the platform (the store owns the
 *   price, so no web price schedule is required).
 * - `accountToken()` is a stable, non-reversible UUID per user, sent as
 *   StoreKit `appAccountToken` / Play `obfuscatedAccountId`; when a
 *   transaction carries it, the backend refuses to credit it to another
 *   account.
 */
class InAppPurchaseCatalog
{
    public const array PLATFORMS = ['ios', 'android'];

    public function __construct(
        private readonly StoryPhotoQuota $quota,
        private readonly AppStoreVerifier $appStore,
        private readonly GooglePlayVerifier $googlePlay,
    ) {}

    public function configured(string $platform): bool
    {
        return match ($platform) {
            'ios' => $this->appStore->configured(),
            'android' => $this->googlePlay->configured(),
            default => false,
        };
    }

    public function offerFor(LegacyStory $story, string $platform): ?PhotoExpansionPack
    {
        return $this->quota->applicablePacks($story)
            ->first(fn (PhotoExpansionPack $pack) => $pack->storeProductId($platform) !== null);
    }

    /** Includes paused/archived packs: a purchase paid before a pause is still honoured. */
    public function packForProduct(string $platform, string $productId): ?PhotoExpansionPack
    {
        $column = match ($platform) {
            'ios' => 'apple_product_id',
            'android' => 'google_product_id',
            default => null,
        };

        return $column === null ? null : PhotoExpansionPack::query()->where($column, $productId)->first();
    }

    public function accountToken(User $user): string
    {
        $hash = hash_hmac('sha256', 'iap-account:'.$user->getKey(), (string) config('app.key'));

        // RFC 4122 layout (version 4 / variant 10xx): StoreKit requires a UUID.
        $hex = substr($hash, 0, 32);
        $hex[12] = '4';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
