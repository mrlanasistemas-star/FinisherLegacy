<?php

use App\Actions\LegacyTags\ConfirmTagActivation;
use App\Actions\LegacyTags\GenerateActivationCodes;
use App\Actions\LegacyTags\ManageTag;
use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\AthleteOwnedProduct;
use App\Models\InventoryLot;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\LegacyTagActivationIntent;
use App\Models\LegacyTagEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/story-helpers.php';

const FL_NFC_TEST_UID = '04:A2:2B:6C:91:5E:80';

beforeEach(function () {
    Storage::fake('local');
    config(['legacy_tags.public_base_url' => 'https://finisherlegacy.com']);
});

/** @return array{0: LegacyTag, 1: string} a delivered tag and its activation code in clear */
function tagWithCode(array $overrides = []): array
{
    $tag = deliveredTag($overrides);
    $code = app(GenerateActivationCodes::class)->handle([$tag])[0]['code'];

    return [$tag->fresh(), GenerateActivationCodes::format($code)];
}

function startActivation(array $body): TestResponse
{
    return test()->postJson('/api/v1/tags/activation/start', ['platform' => 'android', ...$body]);
}

test('happy path: start → write → confirm activates, binds the chip and opens the story', function () {
    [$tag, $code] = tagWithCode();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $start = startActivation(['activation_code' => strtolower($code), 'nfc_uid' => FL_NFC_TEST_UID])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_to_write')
        ->assertJsonPath('data.tag_code', $tag->public_code)
        ->assertJsonPath('data.write_url', 'https://finisherlegacy.com/t/'.$tag->public_code)
        ->assertJsonPath('data.product.collection', 'Cozumel 2026')
        ->assertJsonPath('data.story', null)
        ->assertJsonStructure(['data' => ['intent_id', 'expires_at', 'product' => ['name', 'variant', 'collection', 'image_url']]]);

    $intentId = $start->json('data.intent_id');

    $confirm = $this->postJson("/api/v1/tags/activation/{$intentId}/confirm", [
        'written_url' => 'https://finisherlegacy.com/t/'.$tag->public_code,
        'nfc_uid' => '04a22b6c915e80',
    ])->assertOk()
        ->assertJsonPath('data.status', 'activated')
        ->assertJsonPath('data.tag_code', $tag->public_code)
        ->assertJsonStructure(['data' => ['story' => ['public_id', 'url', 'edit_url']]]);

    $tag->refresh();
    $story = LegacyStory::query()->where('public_id', $confirm->json('data.story.public_id'))->firstOrFail();

    expect($tag->status)->toBe(LegacyTagStatus::Activated)
        ->and($tag->nfc_uid_hash)->toBe(GenerateActivationCodes::nfcUidHash('04A22B6C915E80'))
        ->and($tag->nfc_uid_hash)->not->toContain('04A22B')
        ->and($story->isOwnedBy($user))->toBeTrue()
        ->and($confirm->json('data.story.edit_url'))->toBe(route('my-legacy.edit', $story->public_id))
        ->and(LegacyTagActivationIntent::query()->where('uuid', $intentId)->first()?->status)->toBe(LegacyTagActivationIntentStatus::Confirmed)
        ->and(LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)->where('type', LegacyTagEventType::NfcBound->value)->count())->toBe(1);
});

test('the activation code is stored only as a keyed hash plus last 4', function () {
    [$tag, $code] = tagWithCode();
    $plain = str_replace('-', '', $code);

    expect($tag->activation_code_hash)->toBe(GenerateActivationCodes::hash($plain))
        ->and($tag->activation_code_hash)->not->toContain($plain)
        ->and($tag->activation_code_last4)->toBe(substr($plain, -4))
        ->and($tag->toArray())->not->toHaveKey('activation_code_hash')
        ->and($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/');

    // Re-running never rotates a printed code.
    expect(app(GenerateActivationCodes::class)->handle([$tag]))->toBe([]);
});

test('the written URL is always the official domain, never input', function () {
    config(['legacy_tags.public_base_url' => 'https://finisherlegacy.com/']);
    [$tag, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());

    startActivation(['activation_code' => $code, 'write_url' => 'https://evil.example/x'])
        ->assertOk()
        ->assertJsonPath('data.write_url', 'https://finisherlegacy.com/t/'.$tag->public_code);
});

test('a wrong code is rejected and brute force is throttled per user + IP', function () {
    [$tag, $code] = tagWithCode();
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    RateLimiter::clear('legacy-activation-code:'.$user->id.'|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        startActivation(['activation_code' => 'ZZZZ-ZZZZ-ZZZ'.$i])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACTIVATION_CODE_INVALID')
            ->assertJsonPath('error.code', 'ACTIVATION_CODE_INVALID')
            ->assertJsonStructure(['message', 'code']);
    }

    // Even the right code is refused while locked out.
    startActivation(['activation_code' => $code])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
        ->assertHeader('Retry-After');

    expect(LegacyTagActivationIntent::query()->count())->toBe(0);
});

test('tag code alone works only for the buyer or the owner — otherwise it looks like a wrong code', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    [$tag] = tagWithCode(['order_id' => $order->id]);

    Sanctum::actingAs(User::factory()->create());
    startActivation(['tag_code' => $tag->displayCode()])->assertStatus(422)->assertJsonPath('code', 'ACTIVATION_CODE_INVALID');

    Sanctum::actingAs($buyer);
    startActivation(['tag_code' => $tag->displayCode()])->assertOk()->assertJsonPath('data.status', 'ready_to_write');
});

test('validation: activation_code or tag_code is required and platform must be known', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tags/activation/start', ['platform' => 'android'])
        ->assertStatus(422)->assertJsonValidationErrors(['activation_code', 'tag_code']);
    $this->postJson('/api/v1/tags/activation/start', ['activation_code' => 'AAAA-AAAA-AAAA', 'platform' => 'windows'])
        ->assertStatus(422)->assertJsonValidationErrors(['platform']);
});

test('a tag activated by someone else answers TAG_ALREADY_ACTIVATED', function () {
    [$tag, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $first = startActivation(['activation_code' => $code])->json('data');
    $this->postJson("/api/v1/tags/activation/{$first['intent_id']}/confirm", ['written_url' => $first['write_url']])->assertOk();

    Sanctum::actingAs(User::factory()->create());
    startActivation(['activation_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('code', 'TAG_ALREADY_ACTIVATED');
});

test('lost, stolen and disabled tags are not activatable', function (string $status) {
    [$tag, $code] = tagWithCode(['status' => $status]);
    Sanctum::actingAs(User::factory()->create());

    startActivation(['activation_code' => $code])
        ->assertStatus(422)
        ->assertJsonPath('code', 'TAG_NOT_ACTIVATABLE')
        ->assertJsonPath('error.details.reason', $status);
})->with(['lost', 'stolen', 'disabled', 'replaced', 'returned']);

test('staff can report a tag stolen and recover it', function () {
    [$story, $owner, $tag] = ownedStory();
    $staff = tagStaff();

    $tag = app(ManageTag::class)->reportMissing($tag, $staff, true, 'Robo reportado por el dueño');
    expect($tag->status)->toBe(LegacyTagStatus::Stolen);

    Sanctum::actingAs($owner);
    startActivation(['tag_code' => $tag->public_code])->assertStatus(422)->assertJsonPath('error.details.reason', 'stolen');

    expect(app(ManageTag::class)->recover($tag, $staff)->status)->toBe(LegacyTagStatus::Activated);
});

test('same user again on an own activated tag gets already_activated and can re-confirm (rewrite)', function () {
    [$tag, $code] = tagWithCode();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $first = startActivation(['activation_code' => $code])->json('data');
    $storyId = $this->postJson("/api/v1/tags/activation/{$first['intent_id']}/confirm", ['written_url' => $first['write_url']])->json('data.story.public_id');

    $again = startActivation(['tag_code' => $tag->public_code])
        ->assertOk()
        ->assertJsonPath('data.status', 'already_activated')
        ->assertJsonPath('data.story.public_id', $storyId)
        ->json('data');

    $this->postJson("/api/v1/tags/activation/{$again['intent_id']}/confirm", ['written_url' => $again['write_url']])
        ->assertOk()
        ->assertJsonPath('data.story.public_id', $storyId);

    // Repeating a confirmed intent is idempotent too.
    $this->postJson("/api/v1/tags/activation/{$first['intent_id']}/confirm", ['written_url' => $first['write_url']])
        ->assertOk()
        ->assertJsonPath('data.story.public_id', $storyId);

    expect(LegacyStory::query()->where('legacy_tag_id', $tag->id)->count())->toBe(1)
        ->and(AthleteOwnedProduct::query()->count())->toBe(1);
});

test('confirm rejects a written URL that is not exactly the issued one', function (string $written) {
    [$tag, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $data = startActivation(['activation_code' => $code])->json('data');

    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => str_replace('{code}', $tag->public_code, $written)])
        ->assertStatus(422)
        ->assertJsonPath('code', 'WRITTEN_URL_MISMATCH');

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Delivered);
})->with([
    'third-party host' => 'https://evil.example/t/{code}',
    'http' => 'http://finisherlegacy.com/t/{code}',
    'trailing query' => 'https://finisherlegacy.com/t/{code}?x=1',
    'other tag' => 'https://finisherlegacy.com/t/ABCDEFGHJKMN',
]);

test('UID mismatch: a unit bound to another chip, or a chip bound to another unit', function () {
    [$tag, $code] = tagWithCode(['nfc_uid_hash' => GenerateActivationCodes::nfcUidHash('04AABBCCDDEEFF')]);
    Sanctum::actingAs(User::factory()->create());

    startActivation(['activation_code' => $code, 'nfc_uid' => FL_NFC_TEST_UID])
        ->assertStatus(422)->assertJsonPath('code', 'TAG_UID_MISMATCH');

    [$other, $otherCode] = tagWithCode();
    startActivation(['activation_code' => $otherCode, 'nfc_uid' => '04:AA:BB:CC:DD:EE:FF'])
        ->assertStatus(422)->assertJsonPath('code', 'TAG_UID_MISMATCH');

    // And at confirm time the chip read back must be the one announced at start.
    $data = startActivation(['activation_code' => $otherCode, 'nfc_uid' => FL_NFC_TEST_UID])->assertOk()->json('data');
    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => $data['write_url'], 'nfc_uid' => '04:11:22:33:44:55:66'])
        ->assertStatus(422)->assertJsonPath('code', 'TAG_UID_MISMATCH');

    expect($other->fresh()->status)->toBe(LegacyTagStatus::Delivered);
});

test('an expired intent can still be confirmed when the URL was written (recovery)', function () {
    [$tag, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $data = startActivation(['activation_code' => $code])->json('data');

    $this->travel(2)->hours();

    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => $data['write_url']])
        ->assertOk()->assertJsonPath('data.status', 'activated');

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Activated);
});

test('an intent past the recovery window answers INTENT_EXPIRED', function () {
    [, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $data = startActivation(['activation_code' => $code])->json('data');

    $this->travel(31)->days();

    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => $data['write_url']])
        ->assertStatus(422)->assertJsonPath('code', 'INTENT_EXPIRED');
});

test('another user cannot confirm or fail my intent', function () {
    [, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $data = startActivation(['activation_code' => $code])->json('data');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => $data['write_url']])->assertNotFound();
    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/fail", ['reason' => 'x'])->assertNotFound();
});

test('concurrent double confirm activates once and returns the same story', function () {
    [$tag, $code] = tagWithCode();
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $data = startActivation(['activation_code' => $code, 'nfc_uid' => FL_NFC_TEST_UID])->json('data');
    $intent = LegacyTagActivationIntent::query()->where('uuid', $data['intent_id'])->firstOrFail();

    // Two handlers holding the same stale model (as two parallel requests would).
    $a = app(ConfirmTagActivation::class)->handle($user, $intent, $data['write_url'], FL_NFC_TEST_UID);
    $b = app(ConfirmTagActivation::class)->handle($user, $intent, $data['write_url'], FL_NFC_TEST_UID);

    expect($a->id)->toBe($b->id)
        ->and(LegacyStory::query()->where('legacy_tag_id', $tag->id)->count())->toBe(1)
        ->and(AthleteOwnedProduct::query()->count())->toBe(1)
        ->and(LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)->where('type', LegacyTagEventType::Activated->value)->count())->toBe(1)
        ->and(LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)->where('type', LegacyTagEventType::NfcBound->value)->count())->toBe(1);
});

test('fail records the problem for support without touching ownership', function () {
    [$tag, $code] = tagWithCode();
    Sanctum::actingAs(User::factory()->create());
    $data = startActivation(['activation_code' => $code, 'platform' => 'ios'])->json('data');

    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/fail", ['reason' => 'write_protected'])->assertNoContent();
    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/fail", ['reason' => str_repeat('x', 121)])->assertStatus(422);

    $event = LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)->where('type', LegacyTagEventType::ActivationFailed->value)->firstOrFail();

    expect($event->notes)->toBe('write_protected')
        ->and($event->properties['platform'])->toBe('ios')
        ->and(LegacyTagActivationIntent::query()->where('uuid', $data['intent_id'])->value('failure_reason'))->toBe('write_protected')
        ->and($tag->fresh()->status)->toBe(LegacyTagStatus::Delivered);

    // A write that actually succeeded after a reported failure can still be confirmed.
    $this->postJson("/api/v1/tags/activation/{$data['intent_id']}/confirm", ['written_url' => $data['write_url']])->assertOk();
});

test('endpoints require a Bearer token', function () {
    $this->postJson('/api/v1/tags/activation/start', ['activation_code' => 'AAAA-AAAA-AAAA', 'platform' => 'android'])->assertUnauthorized();
});

test('GET /tags/{code} tells the app whether it can activate and whether a code is needed', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    [$tag] = tagWithCode(['order_id' => $order->id]);

    $this->getJson("/api/v1/tags/{$tag->public_code}")
        ->assertJsonPath('data.can_activate', true)
        ->assertJsonPath('data.owned_by_me', false)
        ->assertJsonPath('data.activation_requires_code', true);

    Sanctum::actingAs($buyer);
    $this->getJson("/api/v1/tags/{$tag->public_code}")->assertJsonPath('data.activation_requires_code', false);
});

test('new lots get activation codes and a private CSV with them in clear', function () {
    $staff = tagStaff();
    $tags = tagStock(tagVariant(), tagLocation(), 3, $staff);

    expect($tags->every(fn (LegacyTag $t) => $t->activation_code_hash !== null && strlen((string) $t->activation_code_last4) === 4))->toBeTrue();

    $files = Storage::disk('local')->files('exports/activation-codes');
    expect($files)->toHaveCount(1);

    $csv = Storage::disk('local')->get($files[0]);
    $lines = array_values(array_filter(explode("\n", trim((string) $csv))));
    expect($lines)->toHaveCount(4);

    $row = str_getcsv($lines[1]);
    expect(GenerateActivationCodes::hash((string) GenerateActivationCodes::normalize($row[3])))->toBe($tags->firstWhere('serial', $row[0])?->activation_code_hash);
});

test('artisan backfills only missing codes, filtered by lot', function () {
    $staff = tagStaff();
    $tags = tagStock(tagVariant(), tagLocation(), 2, $staff);
    $lot = InventoryLot::query()->findOrFail($tags->first()->inventory_lot_id);
    LegacyTag::query()->whereKey($tags->first()->id)->update(['activation_code_hash' => null, 'activation_code_last4' => null]);
    $kept = $tags->last()->activation_code_hash;
    $other = deliveredTag();

    $this->artisan('legacy-tags:generate-activation-codes', ['--lot' => $lot->code])
        ->expectsOutputToContain('1 códigos generados')
        ->assertSuccessful();

    expect($tags->first()->fresh()->activation_code_hash)->not->toBeNull()
        ->and($tags->last()->fresh()->activation_code_hash)->toBe($kept)
        ->and($other->fresh()->activation_code_hash)->toBeNull();

    $this->artisan('legacy-tags:generate-activation-codes')->assertSuccessful();
    expect($other->fresh()->activation_code_hash)->not->toBeNull();

    $this->artisan('legacy-tags:generate-activation-codes', ['--lot' => 'NOPE'])->assertFailed();
});

test('web NFC endpoints use the same contract with the session', function () {
    [$tag, $code] = tagWithCode();
    $user = User::factory()->create();

    $this->get('/activar/nfc')->assertRedirect();

    $this->actingAs($user)->get('/activar/nfc?code='.$tag->public_code)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('activate/NfcWrite')->where('prefillTagCode', $tag->public_code)->where('officialHost', 'finisherlegacy.com'));

    $data = $this->actingAs($user)->postJson('/activar/nfc/start', ['activation_code' => $code, 'platform' => 'web'])
        ->assertOk()->assertJsonPath('data.status', 'ready_to_write')->json('data');

    $this->actingAs($user)->postJson("/activar/nfc/{$data['intent_id']}/confirm", ['written_url' => 'https://evil.example'])
        ->assertStatus(422)->assertJsonPath('code', 'WRITTEN_URL_MISMATCH');

    $this->actingAs($user)->postJson("/activar/nfc/{$data['intent_id']}/confirm", ['written_url' => $data['write_url'], 'nfc_uid' => FL_NFC_TEST_UID])
        ->assertOk()->assertJsonPath('data.status', 'activated');

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Activated);
});
