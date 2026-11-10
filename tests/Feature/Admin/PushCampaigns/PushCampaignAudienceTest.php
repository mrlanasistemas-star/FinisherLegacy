<?php

/**
 * Push campaigns — audience resolution (docs/renovacion/campanas-push.md
 * §Audiencia): every audience type, notification preferences (the
 * Promociones push default is OFF, an explicit opt-out always wins),
 * inactive accounts and the live estimate.
 */

use App\Actions\Notifications\CampaignAudience;
use App\Enums\OrderPaymentStatus;
use App\Enums\UserStatus;
use App\Models\Athlete;
use App\Models\Collection;
use App\Models\EventParticipant;
use App\Models\LegacyTag;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PushDevice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

function pcaDevice(User $user, bool $active = true): PushDevice
{
    return PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'android',
        'provider' => 'expo', 'token' => 'ExponentPushToken['.Str::random(12).']', 'active' => $active,
    ]);
}

function pcaPref(User $user, string $category, bool $push, bool $inApp = true): void
{
    NotificationPreference::create([
        'user_id' => $user->id, 'scope' => NotificationPreference::SCOPE_CUSTOMER,
        'category' => $category, 'push' => $push, 'in_app' => $inApp, 'email' => false,
    ]);
}

/** @return list<int> */
function pcaIds(string $type, array $params = [], string $category = 'promociones', bool $inbox = true): array
{
    return app(CampaignAudience::class)->query($type, $params, $category, $inbox)
        ->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
}

test('"Todos" reaches only active accounts with an active device and promotions push turned on', function () {
    $optedIn = User::factory()->create();
    pcaDevice($optedIn);
    pcaPref($optedIn, 'promociones', push: true);

    $defaultOff = User::factory()->create();
    pcaDevice($defaultOff);

    $noDevice = User::factory()->create();
    pcaPref($noDevice, 'promociones', push: true);

    $inactiveDevice = User::factory()->create();
    pcaDevice($inactiveDevice, active: false);
    pcaPref($inactiveDevice, 'promociones', push: true);

    $suspended = User::factory()->create(['status' => UserStatus::Suspended]);
    pcaDevice($suspended);
    pcaPref($suspended, 'promociones', push: true);

    expect(pcaIds('all'))->toBe([$optedIn->id]);
});

test('targeted audiences respect explicit opt-outs and include inbox-only users when saved to the center', function () {
    $pushUser = User::factory()->create();
    pcaDevice($pushUser);
    pcaDevice($pushUser);

    $inboxOnly = User::factory()->create();

    $optedOut = User::factory()->create();
    pcaDevice($optedOut);
    pcaPref($optedOut, 'avisos', push: false, inApp: false);

    $pushOff = User::factory()->create();
    pcaDevice($pushOff);
    pcaPref($pushOff, 'avisos', push: false, inApp: true);

    $params = ['user_ids' => [$pushUser->id, $inboxOnly->id, $optedOut->id, $pushOff->id]];

    expect(pcaIds('users', $params, 'avisos'))->toBe(collect([$pushUser->id, $inboxOnly->id, $pushOff->id])->sort()->values()->all())
        // Without the notification-center copy only push-reachable users count.
        ->and(pcaIds('users', $params, 'avisos', inbox: false))->toBe([$pushUser->id]);

    $estimate = app(CampaignAudience::class)->estimate('users', $params, 'avisos', true);

    expect($estimate)->toBe(['users' => 3, 'devices' => 2, 'users_with_push' => 1, 'inbox' => 3]);
});

test('promotions are pushed only to users who opted in, but still reach the inbox by default', function () {
    $optedIn = User::factory()->create();
    pcaDevice($optedIn);
    pcaPref($optedIn, 'promociones', push: true);

    $default = User::factory()->create();
    pcaDevice($default);

    $estimate = app(CampaignAudience::class)->estimate('users', ['user_ids' => [$optedIn->id, $default->id]], 'promociones', true);

    expect($estimate['users'])->toBe(2)
        ->and($estimate['users_with_push'])->toBe(1)
        ->and($estimate['devices'])->toBe(1)
        ->and($estimate['inbox'])->toBe(2);
});

test('event participants are matched by account or by athlete identity, optionally per edition', function () {
    $direct = User::factory()->create();
    $participant = EventParticipant::factory()->create(['user_id' => $direct->id]);
    $eventId = $participant->eventEdition->event_id;

    $viaAthlete = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $viaAthlete->id]);
    $otherEdition = $participant->eventEdition->replicate(['uuid'])->fill(['year' => 2019, 'name' => 'Edición 2019']);
    $otherEdition->uuid = (string) Str::uuid();
    $otherEdition->save();
    EventParticipant::factory()->create(['event_edition_id' => $otherEdition->id, 'athlete_id' => $athlete->id, 'event_race_id' => $participant->event_race_id]);

    $elsewhere = User::factory()->create();
    EventParticipant::factory()->create(['user_id' => $elsewhere->id]);

    expect(pcaIds('event', ['event_id' => $eventId], 'avisos'))->toBe(collect([$direct->id, $viaAthlete->id])->sort()->values()->all())
        ->and(pcaIds('event', ['event_id' => $eventId, 'edition_id' => $otherEdition->id], 'avisos'))->toBe([$viaAthlete->id]);
});

test('product buyers need a paid order; collection audiences are the tag owners', function () {
    $product = Product::factory()->create();

    $buyer = User::factory()->create();
    $pending = User::factory()->create();

    foreach ([[$buyer, OrderPaymentStatus::Paid], [$pending, OrderPaymentStatus::Pending]] as [$user, $status]) {
        $order = Order::factory()->create(['user_id' => $user->id, 'payment_status' => $status]);
        OrderItem::create([
            'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'product_id' => $product->id,
            'name' => $product->name, 'sku' => 'PC-'.Str::random(5), 'quantity' => 1,
            'unit_price_minor' => 1000, 'line_total_minor' => 1000, 'currency' => 'MXN',
        ]);
    }

    expect(pcaIds('product', ['product_id' => $product->id], 'avisos'))->toBe([$buyer->id]);

    $collection = Collection::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['collection_id' => $collection->id])->id]);
    $owner = User::factory()->create();
    LegacyTag::factory()->create(['product_variant_id' => $variant->id, 'owner_athlete_id' => Athlete::factory()->create(['user_id' => $owner->id])->id]);
    LegacyTag::factory()->create(['owner_athlete_id' => Athlete::factory()->create(['user_id' => User::factory()->create()->id])->id]);

    expect(pcaIds('collection', ['collection_id' => $collection->id], 'avisos'))->toBe([$owner->id]);
});

test('role and opted-in audiences', function () {
    Role::findOrCreate('athlete');
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');
    User::factory()->create();

    expect(pcaIds('role', ['role' => 'athlete'], 'avisos'))->toBe([$athlete->id]);

    $subscribed = User::factory()->create();
    pcaPref($subscribed, 'promociones', push: true);
    $unsubscribed = User::factory()->create();
    pcaPref($unsubscribed, 'promociones', push: false);

    expect(pcaIds('opted_in'))->toBe([$subscribed->id]);
});
