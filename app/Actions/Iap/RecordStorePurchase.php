<?php

namespace App\Actions\Iap;

use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Quotas\GrantPhotoExpansionEntitlements;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Exceptions\Iap\IapException;
use App\Models\LegacyStory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\StorePurchase;
use App\Models\User;
use App\Services\Iap\AppStoreVerifier;
use App\Services\Iap\GooglePlayVerifier;
use App\Services\Iap\InAppPurchaseCatalog;
use App\Services\Iap\VerifiedStoreTransaction;
use App\Services\Media\StoryPhotoQuota;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns ONE store transaction (StoreKit 2 / Play Billing) into photo
 * capacity for a Legacy story (docs/renovacion/compras-dentro-de-app.md):
 *
 *  1. Re-read the transaction from the store server-side (never trust the app).
 *  2. Check: the story is the caller's, the store product is a photo
 *     expansion whose scope covers that story, the purchase is completed
 *     (not pending / refunded) and — when the app bound it — the
 *     transaction belongs to this account.
 *  3. In one DB transaction: StorePurchase (unique per platform +
 *     transaction) → paid Order (sales_channel app_store / play_store,
 *     amount and currency from the store when it reports them) + its
 *     OrderItem + Payment → MarkOrderPaid → GrantPhotoExpansionEntitlements.
 *  4. Android: acknowledge the purchase.
 *
 * Idempotent: the same transaction (retry at app start, double tap,
 * "Restaurar compras") returns the existing StorePurchase and grants
 * nothing new. The app finishes the store transaction only after this
 * returned successfully.
 */
class RecordStorePurchase
{
    public function __construct(
        private readonly AppStoreVerifier $appStore,
        private readonly GooglePlayVerifier $googlePlay,
        private readonly InAppPurchaseCatalog $catalog,
        private readonly StoryPhotoQuota $quota,
        private readonly MarkOrderPaid $markOrderPaid,
    ) {}

    /**
     * @param  array{platform: string, product_id: string, story_public_id: string, transaction_id?: string|null, purchase_token?: string|null, price_amount_micros?: int|null, currency?: string|null}  $input
     * @return array{purchase: StorePurchase, replayed: bool}
     *
     * @throws IapException
     */
    public function handle(User $user, array $input): array
    {
        $platform = $input['platform'];

        if (! $this->catalog->configured($platform)) {
            throw IapException::notConfigured($platform);
        }

        $story = LegacyStory::query()->where('public_id', $input['story_public_id'])->first();

        if ($story === null || ! $story->isOwnedBy($user)) {
            throw (new ModelNotFoundException)->setModel(LegacyStory::class);
        }

        $key = $platform === 'ios'
            ? (string) ($input['transaction_id'] ?? '')
            : StorePurchase::androidKey((string) ($input['purchase_token'] ?? ''));

        // Replay before calling the store: cheap and side-effect free.
        $existing = $this->existing($platform, $key);
        if ($existing !== null) {
            return ['purchase' => $this->assertSameAccount($existing, $user), 'replayed' => true];
        }

        $transaction = $platform === 'ios'
            ? $this->appStore->transaction($key)
            : $this->googlePlay->purchase($input['product_id'], (string) ($input['purchase_token'] ?? ''));

        if ($transaction->productId !== $input['product_id']) {
            throw IapException::productMismatch();
        }

        $pack = $this->catalog->packForProduct($platform, $transaction->productId);

        if ($pack === null || ! $this->quota->packCovers($story, $pack)) {
            throw IapException::productMismatch();
        }

        if ($transaction->accountToken !== null && $transaction->accountToken !== $this->catalog->accountToken($user)) {
            throw IapException::otherAccount();
        }

        if ($transaction->state === VerifiedStoreTransaction::STATE_PENDING) {
            throw IapException::pending();
        }

        if ($transaction->state !== VerifiedStoreTransaction::STATE_PURCHASED) {
            throw IapException::notValid((string) $transaction->invalidReason);
        }

        try {
            $purchase = DB::transaction(fn () => $this->grant($user, $story, $pack, $transaction, $input));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request recorded the same transaction first.
            $existing = $this->existing($platform, $key);

            if ($existing === null) {
                throw IapException::storeUnavailable();
            }

            return ['purchase' => $this->assertSameAccount($existing, $user), 'replayed' => true];
        }

        if ($platform === 'android' && ! $transaction->acknowledged
            && $this->googlePlay->acknowledge($transaction->productId, (string) ($input['purchase_token'] ?? ''))) {
            $purchase->update(['acknowledged_at' => now()]);
        }

        return ['purchase' => $purchase, 'replayed' => false];
    }

    private function existing(string $platform, string $key): ?StorePurchase
    {
        return $key === '' ? null : StorePurchase::query()
            ->where('platform', $platform)
            ->where('transaction_key', $key)
            ->first();
    }

    private function assertSameAccount(StorePurchase $purchase, User $user): StorePurchase
    {
        if ($purchase->user_id !== $user->id) {
            throw IapException::otherAccount();
        }

        return $purchase;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function grant(User $user, LegacyStory $story, PhotoExpansionPack $pack, VerifiedStoreTransaction $tx, array $input): StorePurchase
    {
        [$amount, $currency, $amountSource] = $this->price($tx, $input);

        $purchase = StorePurchase::create([
            'platform' => $tx->platform,
            'transaction_key' => $tx->transactionKey,
            'transaction_id' => $tx->transactionId,
            'original_transaction_id' => $tx->originalTransactionId,
            'purchase_token' => $tx->platform === 'android' ? (string) $input['purchase_token'] : null,
            'product_id' => $tx->productId,
            'user_id' => $user->id,
            'legacy_story_id' => $story->id,
            'photo_expansion_pack_id' => $pack->id,
            'status' => StorePurchase::STATUS_GRANTED,
            'environment' => $tx->environment,
            'quantity' => $tx->quantity,
            'amount_minor' => $amount,
            'currency' => $currency,
            'amount_source' => $amountSource,
            'purchased_at' => $tx->purchasedAt,
            'acknowledged_at' => $tx->acknowledged ? now() : null,
            'metadata' => $tx->metadata,
        ]);

        $product = Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->firstOrFail();
        $total = $amount ?? 0;
        $unit = intdiv($total, max(1, $tx->quantity));

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'sales_channel' => $tx->platform === 'ios' ? SalesChannel::AppStore : SalesChannel::PlayStore,
            // Store prices include taxes; Apple/Google remit them.
            'subtotal_minor' => $total,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $total,
            'currency' => $currency,
            'customer_snapshot' => ['name' => $user->name, 'email' => $user->email],
        ]);

        $order->items()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => "{$pack->name} · +{$pack->photos} fotos",
            'sku' => 'PHOTO-EXP-'.$pack->id,
            'quantity' => $tx->quantity,
            'unit_price_minor' => $unit,
            'line_total_minor' => $total,
            'currency' => $currency,
            'metadata' => [
                'kind' => GrantPhotoExpansionEntitlements::ITEM_KIND,
                'legacy_story_id' => $story->id,
                'legacy_story_public_id' => $story->public_id,
                'photo_expansion_pack_id' => $pack->id,
                'product_price_schedule_id' => null,
                'photos' => $pack->photos,
                'tax_rate' => 0.0,
                'tax_included' => true,
                'tax_minor' => 0,
                'store' => $tx->platform === 'ios' ? 'app_store' : 'google_play',
                'store_product_id' => $tx->productId,
                'store_transaction_id' => $tx->transactionId,
                'amount_source' => $amountSource,
            ],
        ]);

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'provider' => $tx->platform === 'ios' ? PaymentProvider::AppStore : PaymentProvider::GooglePlay,
            'method' => PaymentMethod::InAppPurchase,
            'status' => PaymentStatus::Paid,
            'amount_minor' => $total,
            'currency' => $currency,
            'provider_reference' => $tx->transactionId ?? $tx->transactionKey,
            'metadata' => ['store_purchase_uuid' => $purchase->uuid, 'environment' => $tx->environment, 'amount_source' => $amountSource],
            'paid_at' => now(),
        ]);

        $this->markOrderPaid->handle($order);

        $purchase->update(['order_id' => $order->id]);

        return $purchase;
    }

    /**
     * The store's price when it reports one (App Store JWS `price` +
     * `currency`); otherwise what the store SDK showed the buyer (Play's
     * `priceAmountMicros` / currency), flagged as client-reported. Only
     * informative: the money was collected by the store.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int|null, 1: string, 2: string|null}
     */
    private function price(VerifiedStoreTransaction $tx, array $input): array
    {
        if ($tx->amountMinor !== null && $tx->currency !== null) {
            return [$tx->amountMinor, $tx->currency, 'store'];
        }

        $currency = is_string($input['currency'] ?? null) ? strtoupper($input['currency']) : null;
        $micros = isset($input['price_amount_micros']) ? (int) $input['price_amount_micros'] : null;

        if ($currency !== null && preg_match('/^[A-Z]{3}$/', $currency) === 1 && $micros !== null && $micros >= 0) {
            return [AppStoreVerifier::milliunitsToMinor(intdiv($micros, 1000), $currency), $currency, 'client_reported'];
        }

        return [null, $tx->currency ?? (string) config('finisher.photos.currency', 'MXN'), null];
    }
}
