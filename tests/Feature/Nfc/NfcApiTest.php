<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Nfc\AssignNfcTag;
use App\Actions\Nfc\ClaimNfcTag;
use App\Actions\Nfc\GenerateNfcTags;
use App\Exceptions\Nfc\NfcAlreadyClaimedException;
use App\Models\AthleteOwnedProduct;
use App\Models\Event;
use App\Models\NfcTag;
use App\Models\NfcTap;
use App\Models\Product;
use App\Models\User;
use App\Support\Nfc\NfcToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

test('generated tokens are random, url-safe and at least 128 bits', function () {
    $tags = app(GenerateNfcTags::class)->handle(50, User::factory()->create(), 'TEST');

    $tokens = $tags->pluck('token');
    expect($tokens->unique())->toHaveCount(50);

    foreach ($tokens as $token) {
        // 32 chars of a 62-symbol alphabet ≈ 190 bits.
        expect($token)->toMatch('/^[A-Za-z0-9]{32}$/')
            ->and(strlen($token) * log(62, 2))->toBeGreaterThanOrEqual(128)
            ->and(NfcToken::isWellFormed($token))->toBeTrue();
    }

    // Never sequential: consecutive tokens share no common prefix pattern.
    expect($tokens[0])->not->toBe($tokens[1]);
});

test('token extraction accepts a bare token or a scanned URL and rejects junk', function () {
    $token = str_repeat('aB3', 10).'xy';

    expect(NfcToken::extract($token))->toBe($token)
        ->and(NfcToken::extract("https://finisherlegacy.com/n/{$token}"))->toBe($token)
        ->and(NfcToken::extract("https://finisherlegacy.com/n/{$token}?utm=x"))->toBe($token)
        ->and(NfcToken::extract('../../etc/passwd'))->toBeNull()
        ->and(NfcToken::extract('short'))->toBeNull();
});

test('the uid is stored only as a keyed hash and never serialized', function () {
    $tag = NfcTag::factory()->create(['uid_hash' => NfcToken::hashUid('04:A2:3B:11:22:33:80')]);

    expect($tag->uid_hash)->not->toContain('04A23B')
        ->and($tag->toArray())->not->toHaveKey('uid_hash')
        ->and(NfcToken::hashUid('04a23b112233 80'))->toBe($tag->uid_hash);
});

test('GET /api/v1/nfc/{token} returns the exact contract for an unclaimed plate', function () {
    [$plate, $tag] = nfcPlateWithTag();

    $response = $this->getJson("/api/v1/nfc/{$tag->token}");

    $response->assertOk()
        ->assertJsonStructure(['data' => [
            'status', 'owned_by_me', 'can_claim',
            'target' => ['type', 'title', 'subtitle', 'image_url', 'web_url', 'profile_username', 'event_slug'],
        ]])
        ->assertJsonPath('data.status', 'unclaimed')
        ->assertJsonPath('data.owned_by_me', false)
        ->assertJsonPath('data.can_claim', true)
        ->assertJsonPath('data.target.type', 'legacy_plate')
        ->assertJsonPath('data.target.web_url', route('legacy-code.show', $plate->legacyCode->code));

    expect(array_keys($response->json('data')))->toBe(['status', 'owned_by_me', 'can_claim', 'target'])
        ->and(array_keys($response->json('data.target')))->toBe(['type', 'title', 'subtitle', 'image_url', 'web_url', 'profile_username', 'event_slug'])
        // No PII: the athlete name never travels in the resolve payload.
        ->and(json_encode($response->json('data')))->not->toContain($plate->athlete_name);

    expect(NfcTap::query()->where('nfc_tag_id', $tag->id)->where('channel', 'app')->count())->toBe(1);
});

test('GET /api/v1/nfc/{token} reports disabled and unknown tokens honestly', function () {
    $disabled = NfcTag::factory()->disabled()->create();
    $blank = NfcTag::factory()->create();

    $this->getJson("/api/v1/nfc/{$disabled->token}")->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.can_claim', false)
        ->assertJsonPath('data.target', null);

    $this->getJson("/api/v1/nfc/{$blank->token}")->assertOk()->assertJsonPath('data.status', 'disabled');

    $this->getJson('/api/v1/nfc/'.str_repeat('Z', 32))->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
});

test('POST /api/v1/nfc/{token}/claim requires auth, returns the gear item and is idempotent for the owner', function () {
    [$plate, $tag] = nfcPlateWithTag();
    $user = User::factory()->create();

    $this->postJson("/api/v1/nfc/{$tag->token}/claim")->assertUnauthorized();

    $response = $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/nfc/{$tag->token}/claim");

    $response->assertOk()
        ->assertJsonPath('data.kind', 'legacy_plate')
        ->assertJsonPath('data.connected', true)
        ->assertJsonPath('data.connection_label', 'Producto conectado')
        ->assertJsonPath('data.story_url', route('legacy-code.show', $plate->legacyCode->code));

    // The Legacy Code followed the NFC claim (single plate-claim implementation).
    expect($plate->legacyCode->fresh()->claimed_by_user_id)->toBe($user->id);

    // Same shape as /me/gear.
    $gear = $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/me/gear')->assertOk();
    expect(array_keys($gear->json('data.0')))->toBe(array_keys($response->json('data')));

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/nfc/{$tag->token}/claim")->assertOk();

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/nfc/{$tag->token}")
        ->assertJsonPath('data.status', 'claimed')
        ->assertJsonPath('data.owned_by_me', true)
        ->assertJsonPath('data.can_claim', false);
});

test('a second athlete claiming an already claimed tag gets 409 NFC_ALREADY_CLAIMED', function () {
    [, $tag] = nfcPlateWithTag();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->withHeaders(apiAuthHeader($first))->postJson("/api/v1/nfc/{$tag->token}/claim")->assertOk();

    $this->withHeaders(apiAuthHeader($second))->postJson("/api/v1/nfc/{$tag->token}/claim")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NFC_ALREADY_CLAIMED');
});

test('two back-to-back claims of an unowned product resolve to exactly one owner', function () {
    $tag = NfcTag::factory()->create();
    $product = Product::factory()->create();
    app(AssignNfcTag::class)->toProduct($tag, $product, null, User::factory()->create());

    $a = User::factory()->create();
    $b = User::factory()->create();

    $assignment = app(ClaimNfcTag::class)->handle($tag, $a);

    expect(fn () => app(ClaimNfcTag::class)->handle($tag, $b))->toThrow(NfcAlreadyClaimedException::class);

    $owned = AthleteOwnedProduct::query()->findOrFail($assignment->athlete_owned_product_id);
    expect($owned->athlete_id)->toBe($a->athlete()->first()->id)
        ->and(AthleteOwnedProduct::query()->where('product_id', $product->id)->count())->toBe(1);
});

test('/me/gear lists NFC-connected products as "Producto conectado" and keeps the old keys', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $tag = NfcTag::factory()->create();
    $product = Product::factory()->create();
    app(AssignNfcTag::class)->toProduct($tag, $product, null, User::factory()->create());
    app(ClaimNfcTag::class)->handle($tag, $user);

    $response = $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/me/gear')->assertOk();

    $item = $response->json('data.0');
    expect($item)->toHaveKeys(['uuid', 'product_name', 'variant_name', 'status', 'asset_code', 'acquired_at', 'activated_at', 'usage_history'])
        ->and($item['kind'])->toBe('product')
        ->and($item['product_name'])->toBe($product->name)
        ->and($item['connected'])->toBeTrue()
        ->and($item['connection_label'])->toBe('Producto conectado');

    expect($athlete->ownedProducts()->count())->toBe(1);
});

test('an event tag is ownerless, always resolves and cannot be claimed', function () {
    $event = Event::factory()->create();
    $tag = NfcTag::factory()->create();
    app(AssignNfcTag::class)->toEvent($tag, $event, User::factory()->create());

    $this->getJson("/api/v1/nfc/{$tag->token}")
        ->assertJsonPath('data.status', 'claimed')
        ->assertJsonPath('data.can_claim', false)
        ->assertJsonPath('data.target.event_slug', $event->slug);

    $this->withHeaders(apiAuthHeader(User::factory()->create()))
        ->postJson("/api/v1/nfc/{$tag->token}/claim")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'NFC_NOT_CLAIMABLE');
});

test('the resolve endpoint is rate limited', function () {
    $tag = NfcTag::factory()->create();
    RateLimiter::for('nfc-resolve', fn ($request) => Limit::perMinute(3)->by($request->ip()));

    foreach (range(1, 3) as $i) {
        $this->getJson("/api/v1/nfc/{$tag->token}")->assertOk();
    }

    $this->getJson("/api/v1/nfc/{$tag->token}")->assertStatus(429);
});
