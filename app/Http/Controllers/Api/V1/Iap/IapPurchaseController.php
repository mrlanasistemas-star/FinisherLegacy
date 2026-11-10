<?php

namespace App\Http\Controllers\Api\V1\Iap;

use App\Actions\Iap\RecordStorePurchase;
use App\Enums\StoryQuotaKind;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Iap\StoreIapPurchaseRequest;
use App\Models\LegacyStory;
use App\Models\StorePurchase;
use App\Services\Iap\InAppPurchaseCatalog;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\StoryPhotoQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * In-app purchase of story expansions — photos, videos or voice messages
 * (StoreKit 2 / Play Billing) — docs/renovacion/compras-dentro-de-app.md.
 *
 * - `GET /api/v1/iap/offer?story=&platform=&kind=` — which store product
 *   the app should load for this story and kind (default `photos`), with
 *   the price step already resolved server-side (the localized price
 *   always comes from the store SDK, never from here), the billing
 *   period, plus the account token to bind the purchase to this user.
 * - `POST /api/v1/iap/purchases` — verify a transaction with the store
 *   and grant the capacity. 201 on a new grant, 200 on a replay; the app
 *   finishes the store transaction only after either.
 */
class IapPurchaseController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function offer(Request $request, InAppPurchaseCatalog $catalog, StoryPhotoQuota $quota, StoryMediaQuota $mediaQuota): JsonResponse
    {
        $data = $request->validate([
            'story' => ['required', 'string', 'regex:/^[a-z0-9]{8,16}$/'],
            'platform' => ['required', 'string', Rule::in(InAppPurchaseCatalog::PLATFORMS)],
            'kind' => ['nullable', Rule::enum(StoryQuotaKind::class)],
        ]);

        $kind = StoryQuotaKind::tryFrom((string) ($data['kind'] ?? '')) ?? StoryQuotaKind::Photos;

        $user = $this->sanctumUser($request);
        $story = LegacyStory::query()->where('public_id', $data['story'])->firstOrFail();

        abort_unless($story->isOwnedBy($user), 404);

        $configured = $catalog->configured($data['platform']);
        $offer = $configured ? $catalog->offerFor($story, $data['platform'], $kind) : null;
        $pack = $offer['pack'] ?? null;
        $summary = $mediaQuota->summary($story, $kind);

        return $this->respond([
            'story' => $story->public_id,
            'platform' => $data['platform'],
            'kind' => $kind->value,
            'available' => $pack !== null,
            'reason' => ! $configured ? 'not_configured' : ($pack === null ? 'no_product' : null),
            'product_id' => $offer['product_id'] ?? null,
            'pack' => $pack === null ? null : [
                'id' => $pack->id,
                'kind' => $pack->kind->value,
                'name' => $pack->name,
                'description' => $pack->description,
                'units' => $pack->units(),
                'photos' => $pack->photos,
                'billing_period' => $pack->billing_period->value,
                'months' => $pack->billing_period->months(),
                // The price band that matched what the story already holds.
                'step' => ($offer['price'] ?? null)?->stepPayload(),
            ],
            'account_token' => $configured ? $catalog->accountToken($user) : null,
            // Photos keep the first shape of this field; other kinds get theirs.
            'quota' => $kind === StoryQuotaKind::Photos
                ? $quota->summary($story)
                : ['limit' => $summary['limit'], 'used' => $summary['used'], 'remaining' => $summary['remaining'], 'can_expand' => $summary['can_expand'], 'max_seconds' => $summary['max_seconds'], 'plan' => $summary['plan']],
        ]);
    }

    public function store(StoreIapPurchaseRequest $request, RecordStorePurchase $record, StoryPhotoQuota $quota, StoryMediaQuota $mediaQuota): JsonResponse
    {
        $user = $this->sanctumUser($request);

        /** @var array{platform: string, product_id: string, story_public_id: string, transaction_id?: string|null, purchase_token?: string|null, price_amount_micros?: int|null, currency?: string|null} $input */
        $input = $request->validated();

        ['purchase' => $purchase, 'replayed' => $replayed] = $record->handle($user, $input);

        $purchase->loadMissing(['legacyStory', 'photoExpansionPack', 'order']);
        $story = $purchase->legacyStory;

        return $this->respond([
            'purchase' => $this->payload($purchase),
            'replayed' => $replayed,
            'quota' => $story !== null ? ['story' => $story->public_id, ...$quota->summary($story)] : null,
            'quotas' => $story !== null ? $mediaQuota->summaryAll($story) : null,
        ], $replayed ? __('Esta compra ya estaba registrada.') : __('Listo: ampliamos el espacio de tu recuerdo.'), [], $replayed ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StorePurchase $purchase): array
    {
        return [
            'id' => $purchase->uuid,
            'platform' => $purchase->platform,
            'product_id' => $purchase->product_id,
            'status' => $purchase->status,
            'story' => $purchase->legacyStory?->public_id,
            'kind' => $purchase->photoExpansionPack?->kind->value,
            'units' => $purchase->photoExpansionPack !== null ? $purchase->photoExpansionPack->units() * max(1, (int) $purchase->getAttribute('quantity')) : null,
            'billing_period' => $purchase->photoExpansionPack?->billing_period->value,
            'photos' => $purchase->photoExpansionPack !== null ? $purchase->photoExpansionPack->photos * max(1, (int) $purchase->getAttribute('quantity')) : null,
            'order_number' => $purchase->order?->order_number,
            'granted_at' => $purchase->created_at?->toIso8601String(),
            'revoked_at' => $purchase->revoked_at?->toIso8601String(),
        ];
    }
}
