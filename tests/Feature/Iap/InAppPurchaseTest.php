<?php

/*
| In-app purchases of photo expansions (docs/renovacion/compras-dentro-de-app.md):
| server-side verification with mocked App Store Server API / Google Play
| Developer API, idempotency, ownership, product/story checks and refunds
| via App Store Server Notifications V2 and Google RTDN.
*/

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProvider;
use App\Enums\SalesChannel;
use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\StorePurchase;
use App\Models\User;
use App\Services\Iap\InAppPurchaseCatalog;
use App\Services\Media\StoryPhotoQuota;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/iap-helpers.php';

const IAP_GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const IAP_GOOGLE_API = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/com.finisherlegacy.app/purchases/products/';
const IAP_APPLE_PROD = 'https://api.storekit.itunes.apple.com/inApps/v1/transactions/';
const IAP_APPLE_SANDBOX = 'https://api.storekit-sandbox.itunes.apple.com/inApps/v1/transactions/';

beforeEach(function () {
    config(['legacy.stories.max_photos' => 5]);
});

/** Not found in production → found in sandbox (TestFlight), like Apple answers. Stubs are per transaction id. */
function fakeAppleTransaction(array $payload): void
{
    $id = $payload['transactionId'];

    Http::fake([
        IAP_APPLE_PROD.$id => Http::response(['errorCode' => 4040010, 'errorMessage' => 'Transaction id not found.'], 404),
        IAP_APPLE_SANDBOX.$id => Http::response(['signedTransactionInfo' => signAppleJws($payload)]),
    ]);
}

/**
 * Stubs are per purchase token (Http::fake stacks; the first match wins).
 *
 * @param  array<string, mixed>  $purchase
 */
function fakeGooglePurchase(array $purchase, string $token = 'play-token-abc', string $product = 'fotos_10'): void
{
    Http::fake([
        IAP_GOOGLE_TOKEN_URL => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        IAP_GOOGLE_API."{$product}/tokens/{$token}*" => function (HttpRequest $request) use ($purchase) {
            return str_ends_with($request->url(), ':acknowledge')
                ? Http::response('', 204)
                : Http::response(['kind' => 'androidpublisher#productPurchase', 'purchaseTimeMillis' => (string) now()->getTimestampMs(),
                    'consumptionState' => 0, 'orderId' => 'GPA.0000-0000-0000-00001', 'acknowledgementState' => 0, 'purchaseType' => 0,
                    'regionCode' => 'MX', ...$purchase]);
        },
    ]);
}

function postIosPurchase(User $user, string $storyId, string $transactionId, string $productId = 'com.finisherlegacy.app.fotos10')
{
    return test()->postJson('/api/v1/iap/purchases', [
        'platform' => 'ios', 'product_id' => $productId, 'story_public_id' => $storyId, 'transaction_id' => $transactionId,
    ], apiAuthHeader($user));
}

function postAndroidPurchase(User $user, string $storyId, string $token = 'play-token-abc', string $productId = 'fotos_10')
{
    return test()->postJson('/api/v1/iap/purchases', [
        'platform' => 'android', 'product_id' => $productId, 'story_public_id' => $storyId, 'purchase_token' => $token,
        'price_amount_micros' => 99000000, 'currency' => 'MXN',
    ], apiAuthHeader($user));
}

/* ---------------- Configuration ---------------- */

test('without store credentials the endpoints answer 503 IAP_NOT_CONFIGURED', function () {
    [$story, $owner] = ownedStory();
    storePack();
    config(['iap.apple.key_id' => null, 'iap.google.service_account_json' => null]);

    postIosPurchase($owner, $story->public_id, '2000000000000001')->assertStatus(503)->assertJsonPath('error.code', 'IAP_NOT_CONFIGURED');
    postAndroidPurchase($owner, $story->public_id)->assertStatus(503)->assertJsonPath('error.code', 'IAP_NOT_CONFIGURED');
    $this->postJson('/api/webhooks/app-store', ['signedPayload' => 'x'])->assertStatus(503)->assertJsonPath('error.code', 'IAP_NOT_CONFIGURED');
    $this->postJson('/api/webhooks/google-play', [])->assertStatus(503)->assertJsonPath('error.code', 'IAP_NOT_CONFIGURED');

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios", apiAuthHeader($owner))
        ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.reason', 'not_configured');

    expect(Order::query()->count())->toBe(0);
});

test('the offer names the store product for the story and a stable account token', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    $pack = storePack(photos: 10);

    $response = $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios", apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.product_id', 'com.finisherlegacy.app.fotos10')
        ->assertJsonPath('data.pack.id', $pack->id)
        ->assertJsonPath('data.quota.limit', 5);

    expect($response->json('data.account_token'))->toBe(app(InAppPurchaseCatalog::class)->accountToken($owner))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    $this->getJson("/api/v1/iap/offer?story={$story->public_id}&platform=ios", apiAuthHeader(User::factory()->create()))->assertNotFound();
});

/* ---------------- iOS ---------------- */

test('ios: a verified transaction creates a paid app_store order and grants the photos', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    $pack = storePack(photos: 10);
    fakeAppleTransaction(appleTransaction('2000000000000001', 'com.finisherlegacy.app.fotos10', [
        'appAccountToken' => app(InAppPurchaseCatalog::class)->accountToken($owner),
    ]));

    postIosPurchase($owner, $story->public_id, '2000000000000001')
        ->assertCreated()
        ->assertJsonPath('data.replayed', false)
        ->assertJsonPath('data.purchase.status', 'granted')
        ->assertJsonPath('data.quota.limit', 15);

    $order = Order::query()->sole();
    $purchase = StorePurchase::query()->sole();

    expect($order->sales_channel)->toBe(SalesChannel::AppStore)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->total_minor)->toBe(9900)
        ->and($order->currency)->toBe('MXN')
        ->and($order->payments()->sole()->provider)->toBe(PaymentProvider::AppStore)
        ->and($purchase->order_id)->toBe($order->id)
        ->and($purchase->environment)->toBe('Sandbox')
        ->and($purchase->amount_source)->toBe('store')
        ->and(MediaEntitlement::query()->sole()->extra_images)->toBe(10)
        ->and(MediaEntitlement::query()->sole()->photo_expansion_pack_id)->toBe($pack->id)
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);

    // The App Store Server API was called with an ES256 bearer JWT.
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->header('Authorization')[0] ?? '', 'Bearer ey'));
});

test('ios: replaying the same transaction grants nothing new and skips the store', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    storePack();
    fakeAppleTransaction(appleTransaction('2000000000000002', 'com.finisherlegacy.app.fotos10'));

    postIosPurchase($owner, $story->public_id, '2000000000000002')->assertCreated();
    Http::fake()->preventStrayRequests();
    postIosPurchase($owner, $story->public_id, '2000000000000002')->assertOk()->assertJsonPath('data.replayed', true);

    expect(Order::query()->count())->toBe(1)
        ->and(MediaEntitlement::query()->count())->toBe(1)
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);

    // Somebody else replaying it is refused.
    postIosPurchase(User::factory()->create(), $story->public_id, '2000000000000002')->assertNotFound();
});

test('ios: an unknown transaction or a forged signature is rejected', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    storePack();

    Http::fake([
        IAP_APPLE_PROD.'2000000000000003' => Http::response(['errorCode' => 4040010], 404),
        IAP_APPLE_SANDBOX.'2000000000000003' => Http::response(['errorCode' => 4040010], 404),
    ]);
    postIosPurchase($owner, $story->public_id, '2000000000000003')->assertStatus(422)->assertJsonPath('error.code', 'IAP_VERIFICATION_FAILED');

    Http::fake([
        IAP_APPLE_PROD.'2000000000000004' => Http::response(['signedTransactionInfo' => signAppleJws(appleTransaction('2000000000000004', 'com.finisherlegacy.app.fotos10'), trusted: false)]),
    ]);
    postIosPurchase($owner, $story->public_id, '2000000000000004')->assertStatus(422)->assertJsonPath('error.details.reason', 'signature');

    expect(Order::query()->count())->toBe(0)->and(MediaEntitlement::query()->count())->toBe(0);
});

test('ios: a refunded transaction, another account or a store outage never grants', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    storePack();

    fakeAppleTransaction(appleTransaction('2000000000000005', 'com.finisherlegacy.app.fotos10', ['revocationDate' => now()->getTimestampMs(), 'revocationReason' => 0]));
    postIosPurchase($owner, $story->public_id, '2000000000000005')->assertStatus(422)->assertJsonPath('error.code', 'IAP_PURCHASE_NOT_VALID');

    fakeAppleTransaction(appleTransaction('2000000000000006', 'com.finisherlegacy.app.fotos10', ['appAccountToken' => '11111111-1111-4111-8111-111111111111']));
    postIosPurchase($owner, $story->public_id, '2000000000000006')->assertStatus(409)->assertJsonPath('error.code', 'IAP_PURCHASE_OTHER_ACCOUNT');

    Http::fake([IAP_APPLE_PROD.'2000000000000007' => Http::response('', 500)]);
    postIosPurchase($owner, $story->public_id, '2000000000000007')->assertStatus(503)
        ->assertJsonPath('error.code', 'IAP_STORE_UNAVAILABLE')
        ->assertJsonPath('error.details.retryable', true);

    expect(StorePurchase::query()->count())->toBe(0)->and(MediaEntitlement::query()->count())->toBe(0);
});

test('ios: product and story mismatches are rejected', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    storePack();

    // The store says another product than the one the app claims.
    fakeAppleTransaction(appleTransaction('2000000000000008', 'com.finisherlegacy.app.otro'));
    postIosPurchase($owner, $story->public_id, '2000000000000008')->assertStatus(422)->assertJsonPath('error.code', 'IAP_PRODUCT_MISMATCH');

    // A pack scoped to another product does not cover this story.
    $other = Product::factory()->create();
    storePack(apple: 'com.finisherlegacy.app.solo', google: 'solo', attributes: ['product_id' => $other->id]);
    fakeAppleTransaction(appleTransaction('2000000000000009', 'com.finisherlegacy.app.solo'));
    postIosPurchase($owner, $story->public_id, '2000000000000009', 'com.finisherlegacy.app.solo')->assertStatus(422)->assertJsonPath('error.code', 'IAP_PRODUCT_MISMATCH');

    // Someone else's story: 404, never confirms it exists.
    [$foreign] = ownedStory();
    postIosPurchase($owner, $foreign->public_id, '2000000000000010')->assertNotFound();

    expect(MediaEntitlement::query()->count())->toBe(0);
});

test('ios: a REFUND notification revokes the capacity and REFUND_REVERSED restores it', function () {
    configureAppleIap();
    [$story, $owner] = ownedStory();
    storePack(photos: 10);
    $tx = appleTransaction('2000000000000011', 'com.finisherlegacy.app.fotos10');
    fakeAppleTransaction($tx);
    postIosPurchase($owner, $story->public_id, '2000000000000011')->assertCreated();

    $notification = fn (string $type, bool $trusted = true) => signAppleJws([
        'notificationType' => $type,
        'notificationUUID' => (string) str()->uuid(),
        'data' => [
            'bundleId' => 'com.finisherlegacy.app',
            'environment' => 'Sandbox',
            'signedTransactionInfo' => signAppleJws([...$tx, 'revocationDate' => now()->getTimestampMs(), 'revocationReason' => 0]),
        ],
    ], $trusted);

    // Forged: rejected, nothing revoked.
    $this->postJson('/api/webhooks/app-store', ['signedPayload' => $notification('REFUND', false)])->assertStatus(400);
    expect(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);

    $this->postJson('/api/webhooks/app-store', ['signedPayload' => $notification('REFUND')])->assertOk()->assertJsonPath('data.result', 'revoked');
    $this->postJson('/api/webhooks/app-store', ['signedPayload' => $notification('REFUND')])->assertOk();

    expect(StorePurchase::query()->sole()->status)->toBe('revoked')
        ->and(MediaEntitlement::query()->sole()->revoked_at)->not->toBeNull()
        ->and(Order::query()->sole()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(5);

    $this->postJson('/api/webhooks/app-store', ['signedPayload' => $notification('REFUND_REVERSED')])->assertOk()->assertJsonPath('data.result', 'reinstated');
    expect(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);
});

/* ---------------- Android ---------------- */

test('android: a verified purchase creates a play_store order, grants and acknowledges', function () {
    configureGoogleIap();
    [$story, $owner] = ownedStory();
    storePack(photos: 10);
    fakeGooglePurchase(['purchaseState' => 0, 'obfuscatedExternalAccountId' => app(InAppPurchaseCatalog::class)->accountToken($owner)]);

    postAndroidPurchase($owner, $story->public_id)->assertCreated()->assertJsonPath('data.quota.limit', 15);

    $order = Order::query()->sole();
    $purchase = StorePurchase::query()->sole();

    expect($order->sales_channel)->toBe(SalesChannel::PlayStore)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->total_minor)->toBe(9900)
        ->and($purchase->amount_source)->toBe('client_reported')
        ->and($purchase->transaction_id)->toBe('GPA.0000-0000-0000-00001')
        ->and($purchase->transaction_key)->toBe(StorePurchase::androidKey('play-token-abc'))
        ->and($purchase->acknowledged_at)->not->toBeNull()
        ->and($purchase->environment)->toBe('test');

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), ':acknowledge') && $r->hasHeader('Authorization', 'Bearer ya29.test'));
});

test('android: pending, cancelled and replayed purchases', function () {
    configureGoogleIap();
    [$story, $owner] = ownedStory();
    storePack();

    fakeGooglePurchase(['purchaseState' => 2], 'pending-token');
    postAndroidPurchase($owner, $story->public_id, 'pending-token')->assertStatus(409)->assertJsonPath('error.code', 'IAP_PURCHASE_PENDING');

    fakeGooglePurchase(['purchaseState' => 1], 'cancelled-token');
    postAndroidPurchase($owner, $story->public_id, 'cancelled-token')->assertStatus(422)->assertJsonPath('error.code', 'IAP_PURCHASE_NOT_VALID');

    Http::fake([IAP_GOOGLE_TOKEN_URL => Http::response(['access_token' => 'ya29.test']), IAP_GOOGLE_API.'fotos_10/tokens/bogus-token' => Http::response(['error' => ['code' => 400]], 400)]);
    postAndroidPurchase($owner, $story->public_id, 'bogus-token')->assertStatus(422)->assertJsonPath('error.code', 'IAP_VERIFICATION_FAILED');

    expect(MediaEntitlement::query()->count())->toBe(0);

    fakeGooglePurchase(['purchaseState' => 0], 'good-token');
    postAndroidPurchase($owner, $story->public_id, 'good-token')->assertCreated();
    postAndroidPurchase($owner, $story->public_id, 'good-token')->assertOk()->assertJsonPath('data.replayed', true);

    // The same token posted by another account (for their own story).
    [$theirStory, $them] = ownedStory();
    postAndroidPurchase($them, $theirStory->public_id, 'good-token')->assertStatus(409)->assertJsonPath('error.code', 'IAP_PURCHASE_OTHER_ACCOUNT');

    expect(Order::query()->count())->toBe(1)->and(MediaEntitlement::query()->count())->toBe(1);
});

test('android: a voided-purchase RTDN revokes the capacity; bad auth is refused', function () {
    configureGoogleIap();
    [$story, $owner] = ownedStory();
    storePack(photos: 10);
    fakeGooglePurchase(['purchaseState' => 0], 'refund-token');
    postAndroidPurchase($owner, $story->public_id, 'refund-token')->assertCreated();

    $push = fn (array $notification) => [
        'message' => ['data' => base64_encode((string) json_encode(['version' => '1.0', 'packageName' => 'com.finisherlegacy.app', 'eventTimeMillis' => (string) now()->getTimestampMs(), ...$notification])), 'messageId' => '1'],
        'subscription' => 'projects/test/subscriptions/play-rtdn',
    ];
    $voided = $push(['voidedPurchaseNotification' => ['purchaseToken' => 'refund-token', 'orderId' => 'GPA.0000-0000-0000-00001', 'productType' => 2, 'refundType' => 1]]);

    $this->postJson('/api/webhooks/google-play?token=wrong', $voided)->assertUnauthorized();
    expect(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);

    $this->postJson('/api/webhooks/google-play?token=rtdn-test-secret', $voided)->assertOk()->assertJsonPath('data.result', 'revoked');
    $this->postJson('/api/webhooks/google-play?token=rtdn-test-secret', $push(['testNotification' => ['version' => '1.0']]))->assertOk()->assertJsonPath('data.result', 'ignored');

    expect(StorePurchase::query()->sole()->status)->toBe('revoked')
        ->and(Order::query()->sole()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(5);
});

test('android: RTDN accepts a Pub/Sub OIDC token for the configured audience', function () {
    configureGoogleIap();
    config(['iap.google.rtdn.audience' => 'https://finisherlegacy.test/api/webhooks/google-play', 'iap.google.rtdn.service_account_email' => 'pubsub-push@test.iam.gserviceaccount.com']);

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $details = openssl_pkey_get_details($key);
    $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
        'kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
    ]]])]);

    $jwt = function (string $email) use ($key, $b64) {
        $input = $b64((string) json_encode(['alg' => 'RS256', 'kid' => 'k1', 'typ' => 'JWT'])).'.'.$b64((string) json_encode([
            'iss' => 'https://accounts.google.com', 'aud' => 'https://finisherlegacy.test/api/webhooks/google-play',
            'sub' => '123', 'email' => $email, 'email_verified' => true, 'iat' => time(), 'exp' => time() + 600,
        ]));
        openssl_sign($input, $sig, $key, OPENSSL_ALGO_SHA256);

        return $input.'.'.$b64($sig);
    };

    $body = ['message' => ['data' => base64_encode((string) json_encode(['packageName' => 'com.finisherlegacy.app', 'testNotification' => ['version' => '1.0']]))]];

    $this->postJson('/api/webhooks/google-play', $body, ['Authorization' => 'Bearer '.$jwt('pubsub-push@test.iam.gserviceaccount.com')])->assertOk();
    $this->postJson('/api/webhooks/google-play', $body, ['Authorization' => 'Bearer '.$jwt('someone@else.com')])->assertUnauthorized();
    $this->postJson('/api/webhooks/google-play', $body)->assertUnauthorized();
});

/* ---------------- Admin ---------------- */

test('admin sets unique store product ids on a pack', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $pack = storePack(apple: 'com.finisherlegacy.app.a', google: 'pack_a');
    $other = PhotoExpansionPack::create(['name' => 'Otro', 'photos' => 5, 'active' => true]);

    $form = ['name' => 'Otro', 'photos' => 5, 'scope' => 'global', 'active' => true];

    $this->actingAs($admin)->patch(route('admin.quotas.packs.update', $other->id), [...$form, 'apple_product_id' => 'com.finisherlegacy.app.a'])
        ->assertSessionHasErrors('apple_product_id');

    $this->patch(route('admin.quotas.packs.update', $other->id), [...$form, 'apple_product_id' => 'com.finisherlegacy.app.b', 'google_product_id' => 'pack_b'])
        ->assertSessionHasNoErrors();

    expect($other->fresh()->apple_product_id)->toBe('com.finisherlegacy.app.b')
        ->and($other->fresh()->google_product_id)->toBe('pack_b')
        ->and($pack->fresh()->apple_product_id)->toBe('com.finisherlegacy.app.a');

    $this->patch(route('admin.quotas.packs.update', $other->id), [...$form, 'apple_product_id' => '', 'google_product_id' => 'Mayúsculas'])
        ->assertSessionHasErrors('google_product_id');
});
