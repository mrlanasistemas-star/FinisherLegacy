<?php

use App\Enums\BillingPeriod;
use App\Enums\StoryQuotaKind;
use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\PhotoExpansionPack;
use App\Models\User;
use App\Services\Iap\InAppPurchaseCatalog;
use App\Services\Media\StoryMediaQuota;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Iap/iap-helpers.php';
require_once __DIR__.'/story-media-helpers.php';

/*
| In-app purchases for every kind: the offer takes `kind`, resolves the
| price step server-side (each step may have its own store product) and
| the verified purchase grants the right kind — permanent or as a dated
| term. Store APIs are mocked; no real ids, credentials or prices.
*/

beforeEach(function () {
    config(['legacy.stories.max_photos' => 5, 'legacy.stories.max_videos' => 1, 'legacy.stories.max_audios' => 1]);
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
    configureAppleIap();
});

function kindsAppleTransaction(string $id, string $productId, User $owner, array $overrides = []): void
{
    $payload = appleTransaction($id, $productId, ['appAccountToken' => app(InAppPurchaseCatalog::class)->accountToken($owner), ...$overrides]);

    Http::fake([
        'https://api.storekit.itunes.apple.com/inApps/v1/transactions/'.$id => Http::response(['errorCode' => 4040010, 'errorMessage' => 'Transaction id not found.'], 404),
        'https://api.storekit-sandbox.itunes.apple.com/inApps/v1/transactions/'.$id => Http::response(['signedTransactionInfo' => signAppleJws($payload)]),
    ]);
}

function kindsIosPurchase(User $user, string $storyId, string $transactionId, string $productId)
{
    return test()->postJson('/api/v1/iap/purchases', [
        'platform' => 'ios', 'product_id' => $productId, 'story_public_id' => $storyId, 'transaction_id' => $transactionId,
    ], apiAuthHeader($user));
}

test('the offer defaults to photos and answers per kind', function () {
    [$story, $owner] = ownedStory();
    storePack(photos: 10);
    $videos = storePack(3, 'com.fl.videos3', 'videos_3', ['kind' => StoryQuotaKind::Videos, 'billing_period' => BillingPeriod::Annual, 'name' => '+3 videos']);
    $headers = apiAuthHeader($owner);

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios", $headers)
        ->assertOk()
        ->assertJsonPath('data.kind', 'photos')
        ->assertJsonPath('data.product_id', 'com.finisherlegacy.app.fotos10')
        ->assertJsonPath('data.pack.units', 10)
        ->assertJsonPath('data.pack.billing_period', 'one_time')
        ->assertJsonPath('data.quota.limit', 5);

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios&kind=videos", $headers)
        ->assertOk()
        ->assertJsonPath('data.kind', 'videos')
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.product_id', 'com.fl.videos3')
        ->assertJsonPath('data.pack.id', $videos->id)
        ->assertJsonPath('data.pack.kind', 'videos')
        ->assertJsonPath('data.pack.units', 3)
        ->assertJsonPath('data.pack.billing_period', 'annual')
        ->assertJsonPath('data.pack.months', 12)
        ->assertJsonPath('data.quota.limit', 1)
        ->assertJsonPath('data.quota.max_seconds', 60);

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios&kind=audio", $headers)
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.reason', 'no_product');

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios&kind=gifs", $headers)->assertStatus(422);
});

test('the offer names the store product of the price step that matches what the story holds', function () {
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 10, amountMinor: 5000, steps: [11 => 8000], attributes: ['apple_product_id' => 'com.fl.fotos10', 'google_product_id' => 'fotos_10']);
    $pack->priceAt()->steps()->sole()->update(['apple_product_id' => 'com.fl.fotos10.b', 'google_product_id' => 'fotos_10_b']);
    $headers = apiAuthHeader($owner);

    // Holds 5 → base band → the pack's own product.
    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios&kind=photos", $headers)
        ->assertJsonPath('data.product_id', 'com.fl.fotos10')
        ->assertJsonPath('data.pack.step.from', 0)
        ->assertJsonPath('data.pack.step.to', 10)
        ->assertJsonPath('data.pack.step.held', 5);

    kindsAppleTransaction('3000000000000001', 'com.fl.fotos10', $owner);
    kindsIosPurchase($owner, $story->public_id, '3000000000000001', 'com.fl.fotos10')
        ->assertCreated()
        ->assertJsonPath('data.purchase.kind', 'photos')
        ->assertJsonPath('data.purchase.units', 10)
        ->assertJsonPath('data.quotas.photos.limit', 15);

    // Holds 15 → second band → that step's product, on both platforms.
    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios", $headers)
        ->assertJsonPath('data.product_id', 'com.fl.fotos10.b')
        ->assertJsonPath('data.pack.step.from', 11)
        ->assertJsonPath('data.pack.step.to', null);

    kindsAppleTransaction('3000000000000002', 'com.fl.fotos10.b', $owner);
    kindsIosPurchase($owner, $story->public_id, '3000000000000002', 'com.fl.fotos10.b')->assertCreated();

    $item = Order::query()->latest('id')->firstOrFail()->items()->sole();

    expect(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Photos))->toBe(25)
        ->and($item->metadata)->toMatchArray(['expansion_kind' => 'photos', 'step_from_units' => 11, 'held_units' => 15, 'step_mismatch' => false, 'store_product_id' => 'com.fl.fotos10.b']);

    // Paying the cheaper band's product when the story is past it: granted
    // (the store already charged) but flagged for review.
    kindsAppleTransaction('3000000000000003', 'com.fl.fotos10', $owner);
    kindsIosPurchase($owner, $story->public_id, '3000000000000003', 'com.fl.fotos10')->assertCreated();

    expect(Order::query()->latest('id')->firstOrFail()->items()->sole()->metadata['step_mismatch'])->toBeTrue()
        ->and(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Photos))->toBe(35);
});

test('a verified purchase grants the kind of its pack, and a term pack gets its dates from the server', function () {
    [$story, $owner] = ownedStory();
    $pack = storePack(3, 'com.fl.videos3', 'videos_3', ['kind' => StoryQuotaKind::Videos, 'billing_period' => BillingPeriod::Annual, 'name' => '+3 videos']);
    $this->freezeSecond();

    kindsAppleTransaction('4000000000000001', 'com.fl.videos3', $owner, ['type' => 'Non-Renewing Subscription']);
    kindsIosPurchase($owner, $story->public_id, '4000000000000001', 'com.fl.videos3')
        ->assertCreated()
        ->assertJsonPath('data.purchase.kind', 'videos')
        ->assertJsonPath('data.purchase.units', 3)
        ->assertJsonPath('data.purchase.billing_period', 'annual')
        ->assertJsonPath('data.quotas.videos.limit', 4)
        ->assertJsonPath('data.quotas.videos.plan.billing_period', 'annual')
        ->assertJsonPath('data.quotas.videos.plan.expired', false)
        ->assertJsonPath('data.quotas.photos.limit', 5);

    $entitlement = MediaEntitlement::query()->sole();

    expect($entitlement->extra_videos)->toBe(3)
        ->and($entitlement->extra_images)->toBe(0)
        ->and($entitlement->photo_expansion_pack_id)->toBe($pack->id)
        ->and($entitlement->billing_period)->toBe(BillingPeriod::Annual)
        ->and($entitlement->expires_at->equalTo(now()->addMonthsNoOverflow(12)))->toBeTrue()
        ->and(Order::query()->sole()->items()->sole()->name)->toBe('+3 videos · +3 videos · 12 meses');

    // Replay: nothing new.
    kindsIosPurchase($owner, $story->public_id, '4000000000000001', 'com.fl.videos3')->assertOk()->assertJsonPath('data.replayed', true);
    expect(MediaEntitlement::query()->count())->toBe(1);

    $this->travelTo(now()->addMonthsNoOverflow(12)->addDay());
    expect(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Videos))->toBe(1)
        ->and(PhotoExpansionPack::query()->count())->toBe(1);

    $this->getJson("/api/v1/stories/{$story->public_id}/quota", apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.kinds.videos.plan.expired', true)
        ->assertJsonPath('data.kinds.videos.limit', 1);
});
