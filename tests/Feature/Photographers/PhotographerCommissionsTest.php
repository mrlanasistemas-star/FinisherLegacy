<?php

use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Actions\Photos\CheckoutEventPhotos;
use App\Enums\EventPhotoStatus;
use App\Enums\PaymentStatus;
use App\Enums\PhotographerStatus;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\Payment;
use App\Models\PhotographerCommissionSetting;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Models\User;
use App\Notifications\PhotographerCommissionChanged;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionResolver;
use App\Services\Commissions\CommissionTerms;
use App\Services\Commissions\PhotographerEarningsReport;
use App\Support\Commerce\PaymentWebhookOutcome;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Photographer commissions (docs/renovacion/comisiones-fotografos.md):
 * admin-editable, effective-dated terms with per-photographer override,
 * the snapshot frozen on each PhotoSale at payment time (minor-unit
 * rounding), refunds reversing the net, payouts using the net, the
 * photographer simulator endpoint, notifications and permission gating.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('event_photo_originals');
    Storage::fake('public');
    config([
        'finisher.payments.default_gateway' => 'openpay',
        'finisher.photos.platform_commission_percent' => 20,
        'finisher.photos.processor_fee_vat_percent' => 16,
        'finisher.photos.processor_fees.openpay' => ['percent' => 2.9, 'fixed_minor' => 250],
        'finisher.photos.gateway_fee_absorbed_by' => 'photographer',
        'finisher.photos.gateway_fee_configured' => false,
    ]);
});

function commissionAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function commissionPhotographer(): PhotographerProfile
{
    return PhotographerProfile::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'display_name' => 'Foto '.Str::random(4),
        'slug' => 'foto-'.Str::lower(Str::random(8)),
        'status' => PhotographerStatus::Approved,
    ]);
}

function commissionPhoto(PhotographerProfile $profile, int $priceMinor = 10000): EventPhoto
{
    $uuid = (string) Str::uuid();
    Storage::disk('event_photo_originals')->put("originals/{$uuid}.jpg", 'jpeg-bytes');

    return EventPhoto::create([
        'uuid' => $uuid,
        'photographer_profile_id' => $profile->id,
        'event_edition_id' => EventEdition::factory()->create()->id,
        'original_path' => "originals/{$uuid}.jpg",
        'preview_path' => "previews/{$uuid}.jpg",
        'thumb_path' => "thumbs/{$uuid}.jpg",
        'price_minor' => $priceMinor,
        'currency' => 'MXN',
        'status' => EventPhotoStatus::Published,
        'published_at' => now(),
    ]);
}

/** Buys the photo and confirms the payment (OpenPay) → returns the PhotoSale. */
function commissionSell(EventPhoto $photo, ?string $reference = null): PhotoSale
{
    $order = app(CheckoutEventPhotos::class)->handle(User::factory()->create(), [$photo->uuid]);

    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'openpay', 'method' => 'online_card',
        'status' => PaymentStatus::Paid, 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
        'provider_reference' => $reference ?? 'ref_'.Str::random(8), 'paid_at' => now(),
    ]);
    app(MarkOrderPaid::class)->handle($order);

    return PhotoSale::query()->where('order_id', $order->id)->sole();
}

function globalTerms(float $percent, array $extra = []): PhotographerCommissionSetting
{
    return PhotographerCommissionSetting::create([
        'uuid' => (string) Str::uuid(),
        'scope' => 'global',
        'commission_percent' => $percent,
        'gateway_fee_absorbed_by' => 'photographer',
        'effective_from' => now()->subMinute(),
        ...$extra,
    ]);
}

test('the sale snapshot freezes rate, commission, gateway fee and net with minor-unit rounding', function () {
    globalTerms(17.5, ['gateway_fee_percent' => 2.9, 'gateway_fee_fixed_minor' => 250]);
    $sale = commissionSell(commissionPhoto(commissionPhotographer(), 9999));

    // commission = round(9999 × 17.5%) = round(1749.825) = 1750
    // gateway    = round((9999 × 2.9% + 250) × 1.16) = round(626.366) = 626
    expect($sale->commission_rate)->toBe(17.5)
        ->and($sale->commission_minor)->toBe(1750)
        ->and($sale->gateway_fee_minor)->toBe(626)
        ->and($sale->photographer_net_minor)->toBe(9999 - 1750 - 626)
        ->and($sale->commission_source)->toBe('global')
        ->and($sale->commission_setting_id)->not->toBeNull()
        ->and($sale->gateway_fee_absorbed_by)->toBe('photographer')
        ->and($sale->gross_minor)->toBe($sale->commission_minor + $sale->gateway_fee_minor + $sale->photographer_net_minor);
});

test('when Finisher absorbs the gateway fee the photographer net is gross minus commission only', function () {
    globalTerms(20, ['gateway_fee_absorbed_by' => 'finisher', 'gateway_fee_percent' => 2.9, 'gateway_fee_fixed_minor' => 250]);
    $sale = commissionSell(commissionPhoto(commissionPhotographer(), 10000));

    expect($sale->commission_minor)->toBe(2000)
        ->and($sale->gateway_fee_minor)->toBe(626)
        ->and($sale->photographer_net_minor)->toBe(8000)
        ->and($sale->gateway_fee_absorbed_by)->toBe('finisher');

    $split = app(CommissionCalculator::class)->split(10000, app(CommissionResolver::class)->termsFor(null));
    expect($split['finisher_take_minor'])->toBe(2000 - 626);
});

test('the calculator never produces a negative net and always adds up', function () {
    $terms = new CommissionTerms(90, 'global', null, null, 'openpay', 10, 5000, 16, true, 'photographer');

    foreach ([0, 1, 2000, 4999, 123457] as $gross) {
        $split = app(CommissionCalculator::class)->split($gross, $terms);

        expect($split['photographer_net_minor'])->toBeGreaterThanOrEqual(0)
            ->and($split['processor_fee_minor'] + $split['platform_fee_minor'] + $split['photographer_net_minor'])->toBe($gross);
    }
});

test('without admin settings the env config is used and the gateway fee is flagged as not configured', function () {
    $terms = app(CommissionResolver::class)->termsFor(null);

    expect($terms->commissionPercent)->toBe(20.0)
        ->and($terms->source)->toBe('config')
        ->and($terms->gatewayFeeConfigured)->toBeFalse()
        ->and($terms->gatewayFeePercent)->toBe(2.9);

    globalTerms(20, ['gateway_fee_percent' => 3.1, 'gateway_fee_fixed_minor' => 0]);
    $terms = app(CommissionResolver::class)->termsFor(null);

    expect($terms->gatewayFeeConfigured)->toBeTrue()->and($terms->gatewayFeePercent)->toBe(3.1);
});

test('a rate change never recalculates past sales and a scheduled change waits for its date', function () {
    Notification::fake();
    $profile = commissionPhotographer();
    $before = commissionSell(commissionPhoto($profile));
    expect($before->commission_minor)->toBe(2000);

    $this->actingAs(commissionAdmin())->post('/admin/photographer-commissions/global', [
        'commission_percent' => 30,
        'gateway_fee_absorbed_by' => 'photographer',
    ])->assertSessionHasNoErrors();

    $after = commissionSell(commissionPhoto($profile));

    expect($before->fresh()->commission_minor)->toBe(2000)
        ->and((float) $before->fresh()->commission_percent)->toBe(20.0)
        ->and($after->commission_minor)->toBe(3000);

    // Scheduled for a future day: today's sales keep 30%.
    $this->post('/admin/photographer-commissions/global', [
        'commission_percent' => 25,
        'gateway_fee_absorbed_by' => 'photographer',
        'effective_from' => now()->addDays(3)->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(commissionSell(commissionPhoto($profile))->commission_minor)->toBe(3000)
        ->and(app(CommissionResolver::class)->upcomingFor($profile)['commission_percent'])->toBe(25.0);

    $this->travelTo(now()->addDays(3)->startOfDay()->addHour());
    expect(commissionSell(commissionPhoto($profile))->commission_minor)->toBe(2500)
        ->and($after->fresh()->commission_minor)->toBe(3000);
});

test('effective dates in the past are rejected', function () {
    $this->actingAs(commissionAdmin())->post('/admin/photographer-commissions/global', [
        'commission_percent' => 15,
        'gateway_fee_absorbed_by' => 'photographer',
        'effective_from' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('effective_from');

    expect(PhotographerCommissionSetting::query()->count())->toBe(0);
});

test('a per-photographer override wins over the global rate until it is removed', function () {
    Notification::fake();
    globalTerms(20);
    $vip = commissionPhotographer();
    $other = commissionPhotographer();
    $admin = commissionAdmin();

    $this->actingAs($admin)->post("/admin/photographer-commissions/fotografos/{$vip->uuid}", ['commission_percent' => 12.5])
        ->assertSessionHasNoErrors();

    $resolver = app(CommissionResolver::class);
    expect($resolver->termsFor($vip)->commissionPercent)->toBe(12.5)
        ->and($resolver->termsFor($vip)->source)->toBe('override')
        ->and($resolver->termsFor($other)->commissionPercent)->toBe(20.0);

    $sale = commissionSell(commissionPhoto($vip, 10000));
    expect($sale->commission_minor)->toBe(1250)->and($sale->commission_source)->toBe('override');

    // A global change does not touch the override.
    $this->post('/admin/photographer-commissions/global', ['commission_percent' => 35, 'gateway_fee_absorbed_by' => 'photographer']);
    expect($resolver->termsFor($vip)->commissionPercent)->toBe(12.5)
        ->and($resolver->termsFor($other)->commissionPercent)->toBe(35.0);

    // Removing the override (null %) returns to the global rate.
    $this->travel(1)->seconds();
    $this->post("/admin/photographer-commissions/fotografos/{$vip->uuid}", ['commission_percent' => null]);
    expect($resolver->termsFor($vip)->commissionPercent)->toBe(35.0)
        ->and($sale->fresh()->commission_minor)->toBe(1250);
});

test('photographers are notified of a global change with its effective date, override holders are not', function () {
    Notification::fake();
    globalTerms(20);
    $regular = commissionPhotographer();
    $vip = commissionPhotographer();
    PhotographerCommissionSetting::create([
        'uuid' => (string) Str::uuid(), 'scope' => 'photographer', 'photographer_profile_id' => $vip->id,
        'commission_percent' => 10, 'effective_from' => now()->subMinute(),
    ]);
    $date = now()->addDays(5)->toDateString();

    $this->actingAs(commissionAdmin())->post('/admin/photographer-commissions/global', [
        'commission_percent' => 22,
        'gateway_fee_absorbed_by' => 'photographer',
        'effective_from' => $date,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($regular->user, PhotographerCommissionChanged::class, function ($n, $channels) use ($date) {
        return $n->commissionPercent === 22.0
            && $n->previousPercent === 20.0
            && $n->effectiveFrom->toDateString() === $date
            && in_array('database', $channels, true) && in_array('mail', $channels, true);
    });
    Notification::assertNotSentTo($vip->user, PhotographerCommissionChanged::class);

    // An override change notifies just that photographer.
    $this->post("/admin/photographer-commissions/fotografos/{$regular->uuid}", ['commission_percent' => 15]);
    Notification::assertSentToTimes($regular->user, PhotographerCommissionChanged::class, 2);
});

test('changes are audit-logged and only pending changes can be cancelled', function () {
    Notification::fake();
    $admin = commissionAdmin();

    $this->actingAs($admin)->post('/admin/photographer-commissions/global', [
        'commission_percent' => 18, 'gateway_fee_absorbed_by' => 'photographer',
        'effective_from' => now()->addDays(2)->toDateString(),
    ]);
    $pending = PhotographerCommissionSetting::query()->sole();

    expect(Activity::query()->where('log_name', 'commissions')->where('causer_id', $admin->id)->count())->toBe(1);

    $this->delete("/admin/photographer-commissions/cambios/{$pending->uuid}")->assertSessionHasNoErrors();
    expect($pending->fresh()->cancelled_at)->not->toBeNull()
        ->and(app(CommissionResolver::class)->upcomingFor(null))->toBeNull();

    $inForce = globalTerms(19);
    $this->delete("/admin/photographer-commissions/cambios/{$inForce->uuid}")->assertSessionHasErrors('setting');
    expect($inForce->fresh()->cancelled_at)->toBeNull();
});

test('a full refund reverses the net and the payout excludes it; partial refunds reverse nothing', function () {
    $profile = commissionPhotographer();
    $sale = commissionSell(commissionPhoto($profile), 'tr_photo_full');
    $kept = commissionSell(commissionPhoto($profile), 'tr_photo_partial');

    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_partial', 'tr_photo_partial', PaymentStatus::PartiallyRefunded, 10000, 'MXN', refundedMinor: 1000));
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_full', 'tr_photo_full', PaymentStatus::Refunded, 10000, 'MXN', refundedMinor: 10000));

    $sale->refresh();
    expect($sale->refunded_at)->not->toBeNull()
        ->and($sale->reversed_net_minor)->toBe($sale->photographer_net_minor)
        ->and($sale->reversed_commission_minor)->toBe($sale->commission_minor)
        ->and($sale->payout_status)->toBe('reversed')
        ->and($kept->fresh()->refunded_at)->toBeNull();

    $this->actingAs(commissionAdmin())->post("/admin/photographers/{$profile->uuid}/payouts", ['reference' => 'SPEI-1'])
        ->assertSessionHasNoErrors();

    $payout = PhotographerPayout::query()->sole();
    expect($payout->amount_minor)->toBe($kept->photographer_net_minor)
        ->and($payout->sales_count)->toBe(1)
        ->and($sale->fresh()->photographer_payout_id)->toBeNull();
});

test('refunding a sale already paid out claws its net back from the next payout', function () {
    $profile = commissionPhotographer();
    $admin = commissionAdmin();
    $first = commissionSell(commissionPhoto($profile), 'tr_paid_then_refunded');

    $this->actingAs($admin)->post("/admin/photographers/{$profile->uuid}/payouts");
    expect($first->fresh()->payout_status)->toBe('paid');

    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_late_refund', 'tr_paid_then_refunded', PaymentStatus::Refunded, 10000, 'MXN', refundedMinor: 10000));
    expect($first->fresh()->refunded_at)->not->toBeNull()->and($first->fresh()->payout_status)->toBe('paid');

    $report = app(PhotographerEarningsReport::class)->rows();
    expect($report[0]['clawback_minor'])->toBe($first->photographer_net_minor)
        ->and($report[0]['due_minor'])->toBe(-$first->photographer_net_minor);

    // Not enough new sales yet → nothing is recorded.
    $this->post("/admin/photographers/{$profile->uuid}/payouts")->assertSessionHasErrors('payout');

    $second = commissionSell(commissionPhoto($profile, 20000));
    $this->post("/admin/photographers/{$profile->uuid}/payouts")->assertSessionHasNoErrors();

    $payout = PhotographerPayout::query()->latest('id')->first();
    expect($payout->amount_minor)->toBe($second->photographer_net_minor - $first->photographer_net_minor)
        ->and($payout->reversals_minor)->toBe($first->photographer_net_minor)
        ->and($payout->reversals_count)->toBe(1)
        ->and($first->fresh()->reversal_payout_id)->toBe($payout->id);
});

test('an admin can reverse a single sale manually, once', function () {
    $profile = commissionPhotographer();
    $sale = commissionSell(commissionPhoto($profile));

    $this->actingAs(commissionAdmin())
        ->post("/admin/photographer-commissions/ventas/{$sale->uuid}/revertir", ['reason' => 'Reembolso parcial'])
        ->assertSessionHasNoErrors();
    $reversedAt = $sale->fresh()->refunded_at;

    $this->post("/admin/photographer-commissions/ventas/{$sale->uuid}/revertir", ['reason' => 'otra vez']);

    expect($sale->fresh()->reversal_reason)->toBe('Reembolso parcial')
        ->and($sale->fresh()->refunded_at->equalTo($reversedAt))->toBeTrue();
});

test('the photographer simulator endpoint returns the same split a sale would freeze', function () {
    globalTerms(20, ['gateway_fee_percent' => 2.9, 'gateway_fee_fixed_minor' => 250]);
    $profile = commissionPhotographer();
    $profile->user->assignRole('photographer');
    PhotographerCommissionSetting::create([
        'uuid' => (string) Str::uuid(), 'scope' => 'photographer', 'photographer_profile_id' => $profile->id,
        'commission_percent' => 15, 'effective_from' => now()->subMinute(),
    ]);

    $response = $this->actingAs($profile->user)->getJson('/fotografo/simulador?price_minor=14900')->assertOk();

    // 15% of 149.00 = 22.35 ; gateway round((14900 × 2.9% + 250) × 1.16) = 791
    $response->assertJsonPath('split.platform_fee_minor', 2235)
        ->assertJsonPath('split.processor_fee_minor', 791)
        ->assertJsonPath('split.photographer_net_minor', 14900 - 2235 - 791)
        ->assertJsonPath('rules.platform_commission_percent', 15)
        ->assertJsonPath('rules.commission_source', 'override')
        ->assertJsonPath('rules.gateway_fee_configured', true)
        ->assertJsonCount(5, 'price_points');

    $sale = commissionSell(commissionPhoto($profile, 14900));
    expect($sale->photographer_net_minor)->toBe($response->json('split.photographer_net_minor'));

    $this->getJson('/fotografo/simulador')->assertUnprocessable();
});

test('commission settings, reports and reversals are permission-gated', function () {
    $profile = commissionPhotographer();
    $sale = commissionSell(commissionPhoto($profile));

    $photosOnly = User::factory()->create();
    $photosOnly->givePermissionTo(['dashboard.admin.view', 'photos.manage']);

    foreach ([
        ['get', '/admin/photographer-commissions'],
        ['get', '/admin/photographer-commissions/reporte'],
        ['post', '/admin/photographer-commissions/global'],
        ['post', "/admin/photographer-commissions/fotografos/{$profile->uuid}"],
        ['post', "/admin/photographer-commissions/ventas/{$sale->uuid}/revertir"],
    ] as [$method, $url]) {
        $this->actingAs($photosOnly)->{$method}($url, ['commission_percent' => 1, 'gateway_fee_absorbed_by' => 'finisher', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($profile->user)->{$method}($url)->assertForbidden();
    }

    expect(PhotographerCommissionSetting::query()->count())->toBe(0)
        ->and($sale->fresh()->refunded_at)->toBeNull();

    $admin = commissionAdmin();
    $this->actingAs($admin)->get('/admin/photographer-commissions')->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/photographers/Commissions')->where('current.commission_percent', 20));
    $this->get('/admin/photographer-commissions/reporte')->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/photographers/CommissionReport')
            ->where('rows.0.gross_minor', 10000)
            ->where('rows.0.commission_minor', 2000)
            ->where('rows.0.due_minor', $sale->photographer_net_minor));

    $this->post('/logout');
    $this->get('/admin/photographer-commissions')->assertRedirect();
});
