<?php

/**
 * Tarjeta de ánimo NFC + configurable cheer pricing
 * (docs/renovacion/tarjeta-animo.md): quota rules (free → paid, hard limit,
 * unlimited, unpriced), paid messages delivered only after the SERVER
 * confirms the payment (idempotent, packs → credits), purge of unpaid
 * audio, missing OpenPay keys, the card's `/t/{code}` → cheer page,
 * bind / unbind authorization, and the anonymity setting.
 */

use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\LegacyTags\ActivateTag;
use App\Actions\LegacyTags\GenerateActivationCodes;
use App\Actions\Training\BindCheerCard;
use App\Actions\Training\PurgeUnpaidCheerMessages;
use App\Enums\CheerAfterFree;
use App\Enums\CheerChannel;
use App\Enums\CheerMessageBilling;
use App\Enums\CheerMessageStatus;
use App\Enums\LegacyTagPurpose;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderStatus;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\Athlete;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerPricingSetting;
use App\Models\CheerSenderCredit;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AthleteAlert;
use App\Services\Training\CheerQuota;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/training-helpers.php';

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
    Notification::fake();
    $this->withoutVite();
    config(['training.cheers.submit_per_ip_per_minute' => 100, 'training.cheers.submit_per_ip_per_hour' => 100]);
});

/** Pricing for one channel (amounts in cents). */
function cheerPricing(CheerChannel $channel, array $attributes): CheerPricingSetting
{
    return CheerPricingSetting::query()->updateOrCreate(['channel' => $channel->value], [
        'free_messages' => 1,
        'after_free' => CheerAfterFree::Blocked,
        'hard_limit' => null,
        'price_minor' => null,
        'currency' => 'MXN',
        'price_steps' => [],
        'packs' => [],
        'allow_anonymous' => false,
        ...$attributes,
    ]);
}

/** Fake OpenPay keys so "the site can charge" (no network call is made). */
function fakeOpenPayKeys(): void
{
    config([
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.public_key' => 'pk_test',
        'finisher.payments.openpay.private_key' => 'sk_test',
    ]);
}

function postCheer(CheerLink $link, array $overrides = []): TestResponse
{
    return test()->from('/animo/'.$link->token)->post('/animo/'.$link->token, cheerPayload($overrides));
}

/** A sold Tarjeta de ánimo, optionally bound (activated) to a link. */
function cheerCard(?CheerLink $link = null): LegacyTag
{
    $tag = LegacyTag::factory()->create(['purpose' => LegacyTagPurpose::Cheer, 'status' => LegacyTagStatus::Delivered]);

    return $link !== null ? app(BindCheerCard::class)->bind($tag, $link, null, 'test') : $tag;
}

test('defaults keep today\'s behavior: free messages, names required, nothing paid', function () {
    $link = cheerLinkFor();

    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link, ['sender_name' => ''])->assertSessionHasErrors('sender_name');

    $message = CheerMessage::query()->sole();
    expect($message->billing)->toBe(CheerMessageBilling::Free)
        ->and($message->source)->toBe(CheerChannel::Link)
        ->and($message->status)->toBe(CheerMessageStatus::Visible)
        ->and($link->fresh()->messages_count)->toBe(1);
});

test('after the free quota a blocked channel refuses, a free one keeps accepting', function () {
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 1, 'after_free' => CheerAfterFree::Blocked]);

    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link)->assertSessionHasErrors('form');
    expect(CheerMessage::query()->count())->toBe(1);

    cheerPricing(CheerChannel::Link, ['free_messages' => 1, 'after_free' => CheerAfterFree::Free]);
    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link)->assertSessionHasNoErrors();
    expect(CheerMessage::query()->where('status', 'visible')->count())->toBe(3);
});

test('the hard limit caps every message, free or not', function () {
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 1, 'after_free' => CheerAfterFree::Free, 'hard_limit' => 2]);

    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link)->assertSessionHasErrors('form');

    expect(CheerMessage::query()->count())->toBe(2);
});

test('a paid channel without a price never invents one: it stays blocked', function () {
    fakeOpenPayKeys();
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 0, 'after_free' => CheerAfterFree::Paid, 'price_minor' => null]);

    postCheer($link)->assertSessionHasErrors('form');
    expect(CheerMessage::query()->count())->toBe(0)->and(Order::query()->count())->toBe(0);
});

test('without OpenPay keys a paid message fails gracefully and nothing is stored', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.public_key' => null, 'finisher.payments.openpay.private_key' => null]);
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 0, 'after_free' => CheerAfterFree::Paid, 'price_minor' => 2500]);

    postCheer($link)->assertSessionHasErrors(['form' => 'Por ahora no podemos cobrar mensajes adicionales. Intenta más tarde.']);

    $this->get('/animo/'.$link->token)->assertInertia(fn ($page) => $page
        ->where('pricing.mode', 'paid')
        ->where('pricing.price_minor', 2500)
        ->where('pricing.payment_available', false));

    expect(CheerMessage::query()->count())->toBe(0)->and(Order::query()->count())->toBe(0);
});

test('a paid message waits for the server-confirmed payment, then is delivered once', function () {
    fakeOpenPayKeys();
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 0, 'after_free' => CheerAfterFree::Paid, 'price_minor' => 2500]);

    $response = postCheer($link, ['email' => 'mama@example.com']);
    $message = CheerMessage::query()->sole();
    $order = $message->order;

    $response->assertRedirect("/animo/{$link->token}/pago/{$order->uuid}");
    expect($message->status)->toBe(CheerMessageStatus::PendingPayment)
        ->and($order->user_id)->toBeNull()
        ->and($order->total_minor)->toBe(2500)
        ->and($link->fresh()->messages_count)->toBe(0);

    // Invisible to the athlete and no notification while unpaid.
    $this->getJson('/api/v1/training/messages', apiAuthHeader($link->user))->assertJsonCount(0, 'data');
    Notification::assertNothingSent();

    // The browser coming back from the payment page proves nothing.
    $this->get("/animo/{$link->token}/pago/{$order->uuid}/confirmacion")->assertOk();
    expect($message->fresh()->status)->toBe(CheerMessageStatus::PendingPayment);

    // Server-side confirmation (webhook / re-read charge) → delivered.
    app(MarkOrderPaid::class)->handle($order->fresh());
    app(MarkOrderPaid::class)->handle($order->fresh()); // replayed webhook

    expect($message->fresh()->status)->toBe(CheerMessageStatus::Visible)
        ->and($message->fresh()->paid_at)->not->toBeNull()
        ->and($link->fresh()->messages_count)->toBe(1);
    Notification::assertSentToTimes($link->user, AthleteAlert::class, 1);
});

test('price steps raise the price from the Nth paid message on', function () {
    $setting = cheerPricing(CheerChannel::Link, ['after_free' => CheerAfterFree::Paid, 'price_minor' => 2000, 'price_steps' => [['from_paid' => 3, 'amount_minor' => 1500]]]);

    expect(CheerQuota::priceFor($setting, 1))->toBe(2000)
        ->and(CheerQuota::priceFor($setting, 2))->toBe(2000)
        ->and(CheerQuota::priceFor($setting, 3))->toBe(1500);
});

test('a pack leaves credits for the same sender, spent without paying again', function () {
    fakeOpenPayKeys();
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 0, 'after_free' => CheerAfterFree::Paid, 'price_minor' => 2500, 'packs' => [['messages' => 3, 'amount_minor' => 6000]]]);

    $this->withCookie('cheer_sender', str_repeat('a', 40));
    postCheer($link, ['pack' => 3]);
    $order = CheerMessage::query()->sole()->order;
    expect($order->total_minor)->toBe(6000);

    app(MarkOrderPaid::class)->handle($order->fresh());
    app(MarkOrderPaid::class)->handle($order->fresh());

    $credit = CheerSenderCredit::query()->sole();
    expect($credit->credits_remaining)->toBe(2);

    postCheer($link)->assertSessionHasNoErrors()->assertRedirect('/animo/'.$link->token);
    expect($credit->fresh()->credits_remaining)->toBe(1)
        ->and(CheerMessage::query()->latest('id')->first()->billing)->toBe(CheerMessageBilling::Credit)
        ->and(Order::query()->count())->toBe(1);
});

test('unpaid messages are purged after their window and their order never gets paid', function () {
    fakeOpenPayKeys();
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 0, 'after_free' => CheerAfterFree::Paid, 'price_minor' => 2500]);

    postCheer($link);
    $message = CheerMessage::query()->sole();

    $this->travel(2)->hours();
    expect(app(PurgeUnpaidCheerMessages::class)->handle())->toBe(1);

    $message->refresh();
    expect($message->status)->toBe(CheerMessageStatus::Expired)
        ->and($message->body)->toBeNull()
        ->and($message->order->status)->toBe(OrderStatus::Cancelled);

    // A late payment on the cancelled order never delivers it.
    app(MarkOrderPaid::class)->handle($message->order);
    expect($message->fresh()->status)->toBe(CheerMessageStatus::Expired);
});

test('a bound card opens the cheer page and its messages count as nfc_card', function () {
    $link = cheerLinkFor();
    $card = cheerCard($link);

    expect($card->status)->toBe(LegacyTagStatus::Activated)
        ->and($card->owner_athlete_id)->toBe(Athlete::query()->where('user_id', $link->user_id)->value('id'));

    $this->get('/t/'.$card->public_code)->assertRedirect('/animo/'.$link->token);
    $this->get('/animo/'.$link->token)->assertInertia(fn ($page) => $page->where('via', 'nfc_card'));

    postCheer($link)->assertSessionHasNoErrors();
    postCheer($link)->assertSessionHasNoErrors(); // every tap = another message

    expect(CheerMessage::query()->where('source', 'nfc_card')->where('legacy_tag_id', $card->id)->count())->toBe(2);
});

test('an unbound or deactivated card shows a friendly inactive page', function () {
    $link = cheerLinkFor();
    $card = cheerCard($link);
    app(BindCheerCard::class)->unbind($card, $link->user);

    $this->get('/t/'.$card->public_code)->assertOk()
        ->assertInertia(fn ($page) => $page->component('activate/Unavailable')->where('reason', 'cheer_inactive'));

    $this->get('/t/'.cheerCard()->public_code)->assertOk()
        ->assertInertia(fn ($page) => $page->where('reason', 'cheer_inactive'));
});

test('only the owner can re-bind or deactivate a card, and only to their own links', function () {
    $link = cheerLinkFor();
    $other = cheerLinkFor($link->user);
    $card = cheerCard($link);
    $stranger = User::factory()->create();
    $strangerLink = cheerLinkFor($stranger);

    $this->putJson("/api/v1/training/cards/{$card->uuid}", ['link_uuid' => $other->uuid], apiAuthHeader($stranger))->assertNotFound();
    $this->putJson("/api/v1/training/cards/{$card->uuid}", ['link_uuid' => $strangerLink->uuid], apiAuthHeader($link->user))->assertNotFound();
    $this->deleteJson("/api/v1/training/cards/{$card->uuid}/link", [], apiAuthHeader($stranger))->assertNotFound();

    $this->putJson("/api/v1/training/cards/{$card->uuid}", ['link_uuid' => $other->uuid], apiAuthHeader($link->user))
        ->assertOk()->assertJsonPath('data.link.uuid', $other->uuid)->assertJsonPath('data.active', true);

    $this->getJson('/api/v1/training/cards', apiAuthHeader($link->user))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.offer.product_slug', 'tarjeta-animo-nfc');

    $this->deleteJson("/api/v1/training/cards/{$card->uuid}/link", [], apiAuthHeader($link->user))
        ->assertOk()->assertJsonPath('data.active', false);
    expect($card->fresh()->cheer_link_id)->toBeNull();
});

test('a cheer card never becomes a story and a card owned by someone else is not re-bound', function () {
    $link = cheerLinkFor();
    $card = cheerCard($link);

    expect(fn () => app(BindCheerCard::class)->bind($card, cheerLinkFor(), null))
        ->toThrow(TagAlreadyActivatedException::class);

    $blank = cheerCard();
    expect(fn () => app(ActivateTag::class)->handle($blank, $link->user))
        ->toThrow(TagNotActivatableException::class);
});

test('anonymous messages need the admin setting, show as Anónimo and can still be blocked', function () {
    $link = cheerLinkFor();
    cheerPricing(CheerChannel::Link, ['free_messages' => 10, 'allow_anonymous' => true]);

    postCheer($link, ['sender_name' => '', 'anonymous' => true])->assertSessionHasNoErrors();
    $message = CheerMessage::query()->sole();
    expect($message->anonymous)->toBeTrue();

    $headers = apiAuthHeader($link->user);
    $this->getJson('/api/v1/training/messages', $headers)
        ->assertJsonPath('data.0.sender_name', 'Anónimo')
        ->assertJsonPath('data.0.anonymous', true);

    $this->postJson("/api/v1/training/messages/{$message->uuid}/block-sender", [], $headers)->assertNoContent();
    postCheer($link, ['sender_name' => '', 'anonymous' => true]);
    expect(CheerMessage::query()->count())->toBe(1);
});

test('the admin pricing page is gated and saves amounts typed in pesos', function () {
    $this->actingAs(User::factory()->create())->get('/admin/limites-y-tarifas/mensajes-de-animo')->assertForbidden();

    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/limites-y-tarifas/mensajes-de-animo')->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/quotas/Cheers')->has('channels', 2)
            ->where('channels.0.free_messages', (int) config('training.cheers.max_messages_per_link'))
            ->where('channels.0.after_free', 'blocked'));

    $this->actingAs($admin)->put('/admin/limites-y-tarifas/mensajes-de-animo/nfc_card', [
        'free_messages' => 2, 'after_free' => 'paid', 'hard_limit' => null, 'price' => '19.90',
        'price_steps' => [['from_paid' => 5, 'price' => '15']], 'packs' => [['messages' => 3, 'price' => '49']],
        'allow_anonymous' => true,
    ])->assertSessionHasNoErrors();

    $setting = CheerPricingSetting::for(CheerChannel::NfcCard);
    expect($setting->price_minor)->toBe(1990)
        ->and($setting->validSteps())->toBe([['from_paid' => 5, 'amount_minor' => 1500]])
        ->and($setting->validPacks())->toBe([['messages' => 3, 'amount_minor' => 4900]])
        ->and($setting->allow_anonymous)->toBeTrue();

    $this->actingAs($admin)->put('/admin/limites-y-tarifas/mensajes-de-animo/link', [
        'free_messages' => 1, 'after_free' => 'paid', 'allow_anonymous' => false,
    ])->assertSessionHasErrors('price');
});

test('the app activates a blank card as a Tarjeta de ánimo bound to the chosen link', function () {
    config(['legacy_tags.public_base_url' => 'https://finisherlegacy.com']);
    $link = cheerLinkFor();
    $card = cheerCard();
    $code = GenerateActivationCodes::format(
        app(GenerateActivationCodes::class)->handle([$card])[0]['code'],
    );
    $headers = apiAuthHeader($link->user);

    // Activating it as a story (old clients) is refused with a clear reason.
    $this->postJson('/api/v1/tags/activation/start', ['platform' => 'android', 'activation_code' => $code], $headers)
        ->assertUnprocessable()->assertJsonPath('error.details.reason', 'cheer_card');

    $start = $this->postJson('/api/v1/tags/activation/start', [
        'platform' => 'android', 'activation_code' => $code, 'purpose' => 'cheer', 'cheer_link_uuid' => $link->uuid,
    ], $headers)->assertOk()
        ->assertJsonPath('data.purpose', 'cheer')
        ->assertJsonPath('data.cheer_link.uuid', $link->uuid)
        ->assertJsonPath('data.write_url', 'https://finisherlegacy.com/t/'.$card->public_code);

    $this->postJson('/api/v1/tags/activation/'.$start->json('data.intent_id').'/confirm', [
        'written_url' => 'https://finisherlegacy.com/t/'.$card->public_code,
    ], $headers)->assertOk()
        ->assertJsonPath('data.purpose', 'cheer')
        ->assertJsonPath('data.story', null)
        ->assertJsonPath('data.cheer_link.uuid', $link->uuid);

    expect($card->fresh()->cheer_link_id)->toBe($link->id)
        ->and($card->fresh()->status)->toBe(LegacyTagStatus::Activated)
        ->and(LegacyStory::query()->where('legacy_tag_id', $card->id)->exists())->toBeFalse();
});
