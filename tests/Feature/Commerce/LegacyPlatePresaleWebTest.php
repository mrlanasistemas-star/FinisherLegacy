<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "Habilitar de verdad el pago anticipado" (brief §3-§15): an athlete can
 * buy a Legacy Plate long before the event, with no bib and no result —
 * event_participant_id stays null on the entitlement until one shows up
 * later (brief §4/§21).
 */
beforeEach(function () {
    $this->edition = EventEdition::factory()->create();
    $this->plateModel = LegacyPlateModel::factory()->create();
    $this->plateProduct = Product::factory()->legacyPlate()->create();
    $this->plateVariant = ProductVariant::factory()->create(['product_id' => $this->plateProduct->id, 'base_price_minor' => 130000]);
    ProductPriceSchedule::create([
        'product_id' => $this->plateProduct->id,
        'event_edition_id' => $this->edition->id,
        'price_type' => 'early_presale',
        'amount_minor' => 130000,
        'currency' => 'MXN',
        'active' => true,
    ]);
});

test('the public event page shows a Legacy Plate presale price with no participant required', function () {
    $this->edition->event->update(['status' => 'published']);

    $response = $this->get("/events/{$this->edition->event->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('edition.legacy_plate.price_minor', 130000)
        ->where('edition.legacy_plate.already_purchased', false)
        ->has('edition.legacy_plate.models', 1)
    );
});

test('an athlete can buy a Legacy Plate presale online with no event_participant_id at all', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/carrito/items', [
        'product_variant_id' => $this->plateVariant->id,
        'quantity' => 1,
        'event_edition_id' => $this->edition->id,
        'legacy_plate_model_id' => $this->plateModel->id,
    ])->assertRedirect();

    $response = $this->actingAs($user)->post('/checkout');

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $response->assertRedirect("/mis-pedidos/{$order->uuid}");

    $entitlement = LegacyPlateEntitlement::query()->where('order_item_id', $order->items->first()->id)->firstOrFail();
    expect($entitlement->event_participant_id)->toBeNull()
        ->and($entitlement->event_edition_id)->toBe($this->edition->id)
        ->and($entitlement->legacy_plate_model_id)->toBe($this->plateModel->id)
        ->and($entitlement->status)->toBe(LegacyPlateEntitlementStatus::PendingPayment);
});

test('a paid presale entitlement shows up in "Mis Legacy Plates" even with no result yet', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $this->edition->id,
        'legacy_plate_model_id' => $this->plateModel->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
        'price_type' => 'early_presale',
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard/my-plates');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('plates.0.presale_status', 'paid')
        ->where('plates.0.plate', null)
    );
});

test('the same athlete cannot accidentally buy two Legacy Plate presales for the same event', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $this->edition->id,
        'legacy_plate_model_id' => $this->plateModel->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $this->actingAs($user)->post('/carrito/items', [
        'product_variant_id' => $this->plateVariant->id,
        'quantity' => 1,
        'event_edition_id' => $this->edition->id,
        'legacy_plate_model_id' => $this->plateModel->id,
    ]);

    // Checkout is where the duplicate is caught (brief §11) — the guard
    // lives in App\Actions\Commerce\CheckoutCart, the single place every
    // Legacy Plate line item passes through, not the cart-add step.
    $this->actingAs($user)->post('/checkout')->assertRedirect();

    expect(LegacyPlateEntitlement::query()->where('athlete_id', $athlete->id)->where('event_edition_id', $this->edition->id)->count())->toBe(1);
});

test('the event page shows already_purchased once the athlete has an entitlement', function () {
    $this->edition->event->update(['status' => 'published']);
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $this->edition->id,
        'legacy_plate_model_id' => $this->plateModel->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $response = $this->actingAs($user)->get("/events/{$this->edition->event->slug}");

    $response->assertInertia(fn ($page) => $page->where('edition.legacy_plate.already_purchased', true));
});
