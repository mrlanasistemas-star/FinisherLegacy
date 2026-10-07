<?php

use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\CouponType;
use App\Enums\PhotographerStatus;
use App\Enums\ProductType;
use App\Models\Coupon;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Commerce\PromotionResolver;
use App\Services\Photos\PhotoFeeCalculator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * Phase 2: ofertas (automatic sale prices), product-scoped coupons, the
 * photographer marketplace fee split / registration, and the 3-layout
 * cap for Legacy Plate print layouts.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makePromotion(array $attributes = [], array $productIds = []): Promotion
{
    $promotion = Promotion::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Buen Fin',
        'type' => CouponType::Percentage,
        'value' => 20,
        'applies_to' => 'all',
        'active' => true,
        ...$attributes,
    ]);
    $promotion->products()->sync($productIds);
    app(PromotionResolver::class)->forget();

    return $promotion;
}

test('a running "all products" offer lowers the resolved price but skips the Legacy Plate', function () {
    $variant = ProductVariant::factory()->create(['base_price_minor' => 100000]);
    $plate = Product::factory()->create(['type' => ProductType::LegacyPlate]);
    makePromotion();

    $price = app(ResolveProductPrice::class)->handle($variant->product, $variant);

    expect($price->amountMinor)->toBe(80000)
        ->and($price->originalAmountMinor)->toBe(100000)
        ->and(app(PromotionResolver::class)->bestFor($plate, 100000))->toBeNull();
});

test('an offer scoped to selected products only applies to those products', function () {
    $selected = ProductVariant::factory()->create(['base_price_minor' => 50000]);
    $other = ProductVariant::factory()->create(['base_price_minor' => 50000]);
    makePromotion(['applies_to' => 'products', 'type' => CouponType::FixedAmount, 'value' => 10000], [$selected->product_id]);

    $resolve = app(ResolveProductPrice::class);

    expect($resolve->handle($selected->product, $selected)->amountMinor)->toBe(40000)
        ->and($resolve->handle($other->product, $other)->amountMinor)->toBe(50000);
});

test('paused, future and expired offers never apply', function () {
    $variant = ProductVariant::factory()->create(['base_price_minor' => 100000]);
    makePromotion(['active' => false]);
    makePromotion(['starts_at' => now()->addDay()]);
    makePromotion(['ends_at' => now()->subDay()]);

    expect(app(ResolveProductPrice::class)->handle($variant->product, $variant)->amountMinor)->toBe(100000);
});

test('a product-scoped coupon only discounts the eligible lines', function () {
    $a = Product::factory()->create();
    $b = Product::factory()->create();
    $coupon = Coupon::create([
        'uuid' => (string) Str::uuid(),
        'code' => 'SOLOA',
        'name' => 'Solo A',
        'type' => CouponType::Percentage,
        'value' => 10,
        'applies_to' => 'products',
        'active' => true,
    ]);
    $coupon->products()->sync([$a->id]);

    expect($coupon->eligibleSubtotal([$a->id => 30000, $b->id => 70000]))->toBe(30000);

    $coupon->update(['applies_to' => 'all']);
    expect($coupon->fresh()->eligibleSubtotal([$a->id => 30000, $b->id => 70000]))->toBe(100000);
});

test('an admin creates an offer for selected products', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)->post('/admin/promotions', [
        'name' => 'Rebajas',
        'type' => 'percentage',
        'value' => 15,
        'applies_to' => 'products',
        'product_ids' => [$product->id],
        'active' => true,
    ])->assertSessionHasNoErrors();

    $promotion = Promotion::query()->where('name', 'Rebajas')->firstOrFail();
    expect($promotion->products()->pluck('products.id')->all())->toBe([$product->id]);
});

test('offers require products when scoped to a selection and cap percentages at 90', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post('/admin/promotions', [
        'name' => 'Mal',
        'type' => 'percentage',
        'value' => 95,
        'applies_to' => 'products',
        'product_ids' => [],
    ])->assertSessionHasErrors(['value', 'product_ids']);
});

test('a regular athlete cannot manage offers', function () {
    $this->actingAs(User::factory()->create())->get('/admin/promotions')->assertForbidden();
});

test('the photo fee split always adds back up to the price the photographer set', function () {
    $split = app(PhotoFeeCalculator::class)->split(9900, 3);

    expect($split['processor_fee_minor'] + $split['platform_fee_minor'] + $split['photographer_net_minor'])->toBe(9900)
        ->and($split['platform_fee_minor'])->toBe((int) round(9900 * config('finisher.photos.platform_commission_percent') / 100))
        ->and($split['photographer_net_minor'])->toBeGreaterThan(0);
});

test('a guest registers as a photographer and lands pending on the portal', function () {
    $this->post('/fotografos/registro', [
        'first_name' => 'Ana',
        'last_name' => 'Lente',
        'email' => 'ana@example.com',
        'password' => 'Sup3r-segura-2026!',
        'password_confirmation' => 'Sup3r-segura-2026!',
        'display_name' => 'Ana Lente Foto',
        'accept_terms' => true,
    ])->assertRedirect(route('photographer.dashboard'));

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
    expect($user->hasRole('photographer'))->toBeTrue()
        ->and($user->photographerProfile->status)->toBe(PhotographerStatus::Pending);

    $this->actingAs($user)->get('/fotografo')->assertOk();
});

test('athletes without the photographer role cannot open the photographer portal', function () {
    $this->actingAs(User::factory()->create())->get('/fotografo')->assertForbidden();
});

test('there are never more than three Legacy Plate layouts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    foreach ([1, 2, 3] as $slot) {
        LegacyPlateModel::factory()->create(['layout_slot' => $slot]);
    }

    $this->actingAs($admin)->post('/admin/legacy-plate-models', [
        'name' => 'Cuarto layout',
        'width_mm' => 200,
        'height_mm' => 80,
    ]);

    expect(LegacyPlateModel::query()->count())->toBe(3);
});

test('a photographer account without a profile is sent to the portal panel, never a 404', function () {
    $user = User::factory()->create();
    $user->assignRole('photographer');

    $this->actingAs($user)->get('/fotografo/fotos')->assertRedirect('/fotografo');
    $this->actingAs($user)->get('/fotografo/ventas')->assertRedirect('/fotografo');
    $this->actingAs($user)->get('/fotografo')->assertOk();
});
