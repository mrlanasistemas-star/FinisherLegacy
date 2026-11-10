<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Services\Media\StoryPhotoQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo quota of a Legacy story and the caller's purchased rights
 * (docs/renovacion/cuotas-y-precios.md). Read-only: the app never
 * hardcodes limits. Buying more space inside the app goes through
 * StoreKit / Play Billing — Api\V1\Iap\IapPurchaseController
 * (docs/renovacion/compras-dentro-de-app.md).
 */
class QuotaController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** Owner only — anyone else gets 404 (never confirms the story exists). */
    public function story(Request $request, string $publicId, StoryPhotoQuota $quota): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $story = LegacyStory::query()->where('public_id', $publicId)->firstOrFail();

        abort_unless($story->isOwnedBy($user), 404);

        return $this->respond([
            'story' => $story->public_id,
            ...$quota->summary($story),
        ]);
    }

    public function entitlements(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $rows = MediaEntitlement::query()
            ->where('user_id', $user->id)
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->with(['legacyStory:id,public_id,title', 'photoExpansionPack:id,name', 'orderItem:id,unit_price_minor,currency'])
            ->latest('id')
            ->limit(200)
            ->get();

        return $this->respond($rows->map(fn (MediaEntitlement $e) => [
            'id' => $e->uuid,
            'type' => 'story_photos',
            'story' => $e->legacyStory?->public_id,
            'story_title' => $e->legacyStory?->title,
            'pack' => $e->photoExpansionPack?->name,
            'photos' => $e->extra_images,
            'price' => $e->orderItem !== null ? StoryPhotoQuota::formatMinor((int) $e->orderItem->unit_price_minor) : null,
            'currency' => $e->orderItem?->currency,
            'active' => $e->revoked_at === null,
            'granted_at' => $e->created_at?->toIso8601String(),
            'revoked_at' => $e->revoked_at?->toIso8601String(),
        ])->values()->all());
    }
}
