<?php

namespace App\Http\Controllers\Api\V1\Iap;

use App\Actions\Iap\RecordStorePurchase;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Iap\StoreIapPurchaseRequest;
use App\Models\LegacyStory;
use App\Models\StorePurchase;
use App\Services\Iap\InAppPurchaseCatalog;
use App\Services\Media\StoryPhotoQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * In-app purchase of photo expansions (StoreKit 2 / Play Billing) —
 * docs/renovacion/compras-dentro-de-app.md.
 *
 * - `GET /api/v1/iap/offer?story=&platform=` — which store product the
 *   app should load for this story (the localized price always comes from
 *   the store SDK, never from here) plus the account token to bind the
 *   purchase to this user.
 * - `POST /api/v1/iap/purchases` — verify a transaction with the store
 *   and grant the capacity. 201 on a new grant, 200 on a replay; the app
 *   finishes the store transaction only after either.
 */
class IapPurchaseController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function offer(Request $request, InAppPurchaseCatalog $catalog, StoryPhotoQuota $quota): JsonResponse
    {
        $data = $request->validate([
            'story' => ['required', 'string', 'regex:/^[a-z0-9]{8,16}$/'],
            'platform' => ['required', 'string', Rule::in(InAppPurchaseCatalog::PLATFORMS)],
        ]);

        $user = $this->sanctumUser($request);
        $story = LegacyStory::query()->where('public_id', $data['story'])->firstOrFail();

        abort_unless($story->isOwnedBy($user), 404);

        $configured = $catalog->configured($data['platform']);
        $pack = $configured ? $catalog->offerFor($story, $data['platform']) : null;

        return $this->respond([
            'story' => $story->public_id,
            'platform' => $data['platform'],
            'available' => $pack !== null,
            'reason' => ! $configured ? 'not_configured' : ($pack === null ? 'no_product' : null),
            'product_id' => $pack?->storeProductId($data['platform']),
            'pack' => $pack === null ? null : [
                'id' => $pack->id,
                'name' => $pack->name,
                'description' => $pack->description,
                'photos' => $pack->photos,
            ],
            'account_token' => $configured ? $catalog->accountToken($user) : null,
            'quota' => $quota->summary($story),
        ]);
    }

    public function store(StoreIapPurchaseRequest $request, RecordStorePurchase $record, StoryPhotoQuota $quota): JsonResponse
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
        ], $replayed ? 'Esta compra ya estaba registrada.' : 'Listo: ampliamos el espacio de tu recuerdo.', [], $replayed ? 200 : 201);
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
            'photos' => $purchase->photoExpansionPack !== null ? $purchase->photoExpansionPack->photos * max(1, (int) $purchase->getAttribute('quantity')) : null,
            'order_number' => $purchase->order?->order_number,
            'granted_at' => $purchase->created_at?->toIso8601String(),
            'revoked_at' => $purchase->revoked_at?->toIso8601String(),
        ];
    }
}
