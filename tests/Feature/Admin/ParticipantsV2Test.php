<?php

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\ResultStatus;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventResult;
use App\Models\LegacyPlateModel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * Participants V2 (product UX consolidation brief §19-§31): event-scoped
 * context, filters applied in SQL before paginate(), and per-event
 * metrics computed from paid Orders only.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->edition = EventEdition::factory()->create();
});

test('without an event selected, no participant list or metrics are returned', function () {
    $response = $this->actingAs($this->admin)->get('/admin/participants');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Index')
        ->where('participants', null)
        ->where('metrics', null));
});

test('the legacy_plate filter is applied in SQL before paginate, never on the paged collection', function () {
    $withPlate = EventParticipant::factory()->count(2)->create(['event_edition_id' => $this->edition->id]);
    EventParticipant::factory()->count(3)->create(['event_edition_id' => $this->edition->id]);

    foreach ($withPlate as $participant) {
        app(CreateLegacyPlateEntitlement::class)->handle([
            'athlete_id' => Athlete::factory()->create()->id,
            'event_edition_id' => $this->edition->id,
            'event_participant_id' => $participant->id,
            'legacy_plate_model_id' => LegacyPlateModel::factory()->create()->id,
            'status' => LegacyPlateEntitlementStatus::Paid,
        ]);
    }

    $response = $this->actingAs($this->admin)->get("/admin/participants?event_edition_id={$this->edition->id}&legacy_plate=paid");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Index')
        ->has('participants.data', 2)
        ->where('participants.total', 2)
        ->where('participants.last_page', 1));
});

test('the product filter only matches participants whose athlete actually bought that product for this event', function () {
    $product = Product::factory()->create(['slug' => 'trisuit']);
    $buyerAthlete = Athlete::factory()->create();
    $buyer = EventParticipant::factory()->create(['event_edition_id' => $this->edition->id, 'athlete_id' => $buyerAthlete->id]);
    EventParticipant::factory()->create(['event_edition_id' => $this->edition->id]);

    $order = Order::factory()->create([
        'athlete_id' => $buyerAthlete->id,
        'event_edition_id' => $this->edition->id,
        'payment_status' => OrderPaymentStatus::Paid,
    ]);
    OrderItem::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'sku' => 'TRI-TEST',
        'quantity' => 1,
        'unit_price_minor' => 189000,
        'line_total_minor' => 189000,
        'currency' => 'MXN',
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/participants?event_edition_id={$this->edition->id}&product=trisuit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Index')
        ->has('participants.data', 1)
        ->where('participants.data.0.id', $buyer->id));
});

test('metrics count units and revenue from paid Orders only, never from unpaid ones', function () {
    $product = Product::factory()->create(['slug' => 'chill-band']);

    $paidOrder = Order::factory()->create(['event_edition_id' => $this->edition->id, 'payment_status' => OrderPaymentStatus::Paid]);
    OrderItem::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $paidOrder->id, 'product_id' => $product->id,
        'name' => $product->name, 'sku' => 'CHILL-TEST', 'quantity' => 3,
        'unit_price_minor' => 29000, 'line_total_minor' => 87000, 'currency' => 'MXN',
    ]);

    $unpaidOrder = Order::factory()->create(['event_edition_id' => $this->edition->id, 'payment_status' => OrderPaymentStatus::Pending]);
    OrderItem::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $unpaidOrder->id, 'product_id' => $product->id,
        'name' => $product->name, 'sku' => 'CHILL-TEST-2', 'quantity' => 10,
        'unit_price_minor' => 29000, 'line_total_minor' => 290000, 'currency' => 'MXN',
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/participants?event_edition_id={$this->edition->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Index')
        ->where('metrics.chill_band', 3)
        ->where('metrics.revenue_minor', 87000));
});

test('the finishers metric only counts finished/verified results', function () {
    $finisher = EventParticipant::factory()->create(['event_edition_id' => $this->edition->id]);
    EventResult::factory()->create(['event_participant_id' => $finisher->id, 'status' => ResultStatus::Finished]);

    $dnf = EventParticipant::factory()->create(['event_edition_id' => $this->edition->id]);
    EventResult::factory()->create(['event_participant_id' => $dnf->id, 'status' => ResultStatus::Dnf]);

    EventParticipant::factory()->create(['event_edition_id' => $this->edition->id]);

    $response = $this->actingAs($this->admin)->get("/admin/participants?event_edition_id={$this->edition->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('metrics.participants', 3)
        ->where('metrics.finishers', 1));
});

test('a participant profile shows the athlete\'s full event history, not just this event', function () {
    $athlete = Athlete::factory()->create();
    $current = EventParticipant::factory()->create(['event_edition_id' => $this->edition->id, 'athlete_id' => $athlete->id]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->actingAs($this->admin)->get("/admin/participants/{$current->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Show')
        ->has('historial', 2));
});

test('the CSV export respects the current filters', function () {
    EventParticipant::factory()->count(2)->create(['event_edition_id' => $this->edition->id]);
    $finisher = EventParticipant::factory()->create(['event_edition_id' => $this->edition->id]);
    EventResult::factory()->create(['event_participant_id' => $finisher->id, 'status' => ResultStatus::Finished]);

    $response = $this->actingAs($this->admin)->get("/admin/participants/export?event_edition_id={$this->edition->id}&result=finished");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $lines = array_filter(explode("\n", $response->streamedContent()));
    expect($lines)->toHaveCount(2); // header + the one finisher
});
