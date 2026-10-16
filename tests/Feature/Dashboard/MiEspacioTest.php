<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PreregistrationStatus;
use App\Models\Athlete;
use App\Models\AthleteProfile;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventPreregistration;
use App\Models\EventRace;
use App\Models\EventResult;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Commerce\OrderProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Mi espacio" (spec §7/§134): Mi legado, Mis eventos, the event story,
 * Mis pedidos and Mi perfil — always the signed-in athlete's own data, with
 * a query count that doesn't grow with their history.
 */
function pastRace(Athlete $athlete, string $date = '-2 months', float $km = 10): EventParticipant
{
    $edition = EventEdition::factory()->create(['event_date' => now()->modify($date)]);
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id, 'distance_value' => $km, 'distance_unit' => 'km']);
    $participant = EventParticipant::factory()->create([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'event_race_id' => $race->id,
    ]);
    EventResult::factory()->create(['event_participant_id' => $participant->id, 'official_time' => '00:48:12']);

    return $participant;
}

test('Mi legado renders for a brand-new athlete with nothing yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('legado', [])
            ->where('nextEvent', null)
            ->where('totals.races', 0)
            ->where('totals.km', 0));
});

test('Mi legado shows the timeline, totals and the next race of the athlete only', function () {
    $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'López']);
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    pastRace($athlete, '-1 year', 21.0975);
    pastRace($athlete, '-2 months', 10);

    $event = Event::factory()->create(['name' => 'Maratón de la Ciudad']);
    $future = EventEdition::factory()->create(['event_id' => $event->id, 'event_date' => now()->addDays(20)]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $future->id]);

    pastRace(Athlete::factory()->create());

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('legado', 3)
            ->where('totals.races', 2)
            ->where('totals.km', 31.1)
            ->where('athlete.first_name', 'Ana')
            ->where('nextEvent.event', 'Maratón de la Ciudad')
            ->where('nextEvent.kind', 'registered')
            ->where('legado.0.upcoming', true)
            ->where('legado.1.official_time', '00:48:12'));
});

test('a pending pre-registration becomes the next event when nothing is registered', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create(['event_date' => now()->addMonth()]);
    EventPreregistration::factory()->create([
        'user_id' => $user->id,
        'event_edition_id' => $edition->id,
        'status' => PreregistrationStatus::Pending,
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('nextEvent.kind', 'preregistered'));
});

test('Mi legado runs a bounded number of queries no matter how long the history is', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    pastRace($athlete);

    $count = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count(); // warm-up: first request boots caches/session
    $few = $count();

    foreach (range(1, 8) as $i) {
        pastRace($athlete, "-{$i} weeks");
    }

    expect($count())->toBe($few);
});

test('Mis eventos splits the races ahead from the ones already run', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    pastRace($athlete);
    $future = EventEdition::factory()->create(['event_date' => now()->addWeeks(3)]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $future->id]);
    EventPreregistration::factory()->create([
        'user_id' => $user->id,
        'event_edition_id' => EventEdition::factory()->create(['event_date' => now()->addMonths(2)])->id,
    ]);
    pastRace(Athlete::factory()->create());

    $this->actingAs($user)->get('/dashboard/my-events')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/MyEvents')
            ->has('upcoming', 2)
            ->where('upcoming.0.kind', 'registered')
            ->where('upcoming.1.kind', 'preregistered')
            ->has('past.data', 1)
            ->where('past.total', 1));
});

test('the old event URL now opens the event story, and stays private', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $mine = pastRace($athlete);
    $theirs = pastRace(Athlete::factory()->create());

    $this->actingAs($user)->get("/dashboard/my-events/{$mine->id}")
        ->assertRedirect("/dashboard/legado/{$mine->id}");
    $this->actingAs($user)->get("/dashboard/my-events/{$theirs->id}")->assertForbidden();
    $this->actingAs($user)->get("/dashboard/legado/{$theirs->id}")->assertForbidden();
});

test('the event story carries its place, distance and result', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = pastRace($athlete, '-3 weeks', 42.195);

    $this->actingAs($user)->get("/dashboard/legado/{$participant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/LegadoShow')
            ->where('story.distance', '42.195 km')
            ->where('story.upcoming', false)
            ->where('result.official_time', '00:48:12')
            ->has('story.city'));
});

function orderFor(User $user, array $attributes = []): Order
{
    $order = Order::factory()->create(['user_id' => $user->id, ...$attributes]);
    $product = Product::factory()->create();
    OrderItem::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'product_id' => $product->id,
        'name' => $product->name, 'sku' => 'SKU-'.Str::random(4), 'quantity' => 1,
        'unit_price_minor' => 50000, 'line_total_minor' => 50000, 'currency' => 'MXN',
    ]);

    return $order;
}

test('Mis pedidos lists only the customer\'s orders, in human words', function () {
    $user = User::factory()->create();
    orderFor($user, ['payment_status' => OrderPaymentStatus::Paid, 'status' => OrderStatus::Confirmed]);
    orderFor(User::factory()->create());

    $this->actingAs($user)->get('/mis-pedidos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Orders')
            ->has('orders.data', 1)
            ->where('orders.data.0.progress', 'Preparando')
            ->has('orders.data.0.preview', 1));
});

test('an order detail shows its journey and is closed to other customers', function () {
    $user = User::factory()->create();
    $order = orderFor($user);
    $other = orderFor(User::factory()->create());

    $this->actingAs($user)->get("/mis-pedidos/{$order->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/OrderShow')
            ->where('progress.headline', 'Pendiente de pago')
            ->where('progress.steps.0.state', 'current')
            ->has('progress.steps', 5)
            ->has('items.0.image_url'));

    $this->actingAs($user)->get("/mis-pedidos/{$other->uuid}")->assertForbidden();
});

test('order progress follows payment and delivery', function () {
    $paid = Order::factory()->make(['payment_status' => OrderPaymentStatus::Paid, 'created_at' => now()]);
    $partly = Order::factory()->make([
        'payment_status' => OrderPaymentStatus::Paid,
        'fulfillment_status' => FulfillmentStatus::PartiallyFulfilled,
        'customer_snapshot' => ['delivery_method' => 'pickup'],
        'created_at' => now(),
    ]);
    $delivered = Order::factory()->make([
        'payment_status' => OrderPaymentStatus::Paid,
        'fulfillment_status' => FulfillmentStatus::Fulfilled,
        'created_at' => now(),
    ]);
    $cancelled = Order::factory()->make(['status' => OrderStatus::Cancelled, 'created_at' => now()]);

    expect(OrderProgress::for($paid)['headline'])->toBe('Preparando')
        ->and(collect(OrderProgress::for($paid)['steps'])->pluck('state')->all())
        ->toBe(['done', 'done', 'current', 'upcoming', 'upcoming'])
        ->and(OrderProgress::for($partly)['headline'])->toBe('Listo para recoger')
        ->and(OrderProgress::for($delivered)['headline'])->toBe('Entregado')
        ->and(collect(OrderProgress::for($delivered)['steps'])->pluck('state')->unique()->all())->toBe(['done'])
        ->and(OrderProgress::for($cancelled)['closed']['label'])->toBe('Pedido cancelado');
});

test('Mi perfil surfaces privacy in human words and the public profile link', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');
    AthleteProfile::factory()->for($user)->create([
        'username' => 'ana.corre',
        'profile_visibility' => 'private',
    ]);

    $this->actingAs($user)->get('/dashboard/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/profile/Show')
            ->where('profile.profile_visibility', 'private')
            ->where('profile.public_url', route('profile.public', 'ana.corre'))
            ->where('visibilityOptions', fn ($options) => collect($options)->contains('label', 'Privado')));

    $this->actingAs($user)->get('/dashboard/profile/edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('visibilityOptions', fn ($options) => collect($options)->pluck('value')->contains('public')));
});
