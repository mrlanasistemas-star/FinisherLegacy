<?php

use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Commerce\RegisterManualPayment;
use App\Actions\Photos\CheckoutEventPhotos;
use App\Enums\EventPhotoStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PhotographerStatus;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Models\User;
use App\Services\Photos\PhotoFeeCalculator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photographer marketplace money + edge cases: which gateway processed the
 * payment, estimated vs. reconciled processing fee, idempotency, explicit
 * handling of every uuid in a checkout, downloads and payouts.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('event_photo_originals');
    Storage::fake('public');
    config([
        'finisher.photos.platform_commission_percent' => 20,
        'finisher.photos.processor_fee_vat_percent' => 16,
        'finisher.photos.processor_fees.openpay' => ['percent' => 2.9, 'fixed_minor' => 250],
        'finisher.photos.processor_fees.stripe' => ['percent' => 3.6, 'fixed_minor' => 300],
    ]);
});

function marketPhotographer(PhotographerStatus $status = PhotographerStatus::Approved): PhotographerProfile
{
    $user = User::factory()->create();

    return PhotographerProfile::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'display_name' => 'Foto '.Str::random(4),
        'slug' => 'foto-'.Str::lower(Str::random(8)),
        'status' => $status,
    ]);
}

function marketPhoto(?PhotographerProfile $profile = null, array $attributes = []): EventPhoto
{
    $profile ??= marketPhotographer();
    $uuid = (string) Str::uuid();
    Storage::disk('event_photo_originals')->put("originals/{$uuid}.jpg", 'jpeg-bytes');

    return EventPhoto::create([
        'uuid' => $uuid,
        'photographer_profile_id' => $profile->id,
        'event_edition_id' => EventEdition::factory()->create()->id,
        'original_path' => "originals/{$uuid}.jpg",
        'preview_path' => "previews/{$uuid}.jpg",
        'thumb_path' => "thumbs/{$uuid}.jpg",
        'price_minor' => 10000,
        'currency' => 'MXN',
        'status' => EventPhotoStatus::Published,
        'published_at' => now(),
        ...$attributes,
    ]);
}

function marketPay(Order $order, string $provider): void
{
    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => $provider, 'method' => 'online_card',
        'status' => PaymentStatus::Paid, 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
        'provider_reference' => 'ref_'.Str::random(6), 'paid_at' => now(),
    ]);
    app(MarkOrderPaid::class)->handle($order);
}

test('the split uses the rate of the gateway that processed the payment and always adds up', function () {
    $calc = app(PhotoFeeCalculator::class);

    $openpay = $calc->split(10000, 1, 'openpay');
    $stripe = $calc->split(10000, 1, 'stripe');
    $manual = $calc->split(10000, 1, 'manual');

    // (10000 × 2.9% + 250) × 1.16 = 626 ; (10000 × 3.6% + 300) × 1.16 = 766
    expect($openpay['processor_fee_minor'])->toBe(626)
        ->and($stripe['processor_fee_minor'])->toBe(766)
        ->and($manual['processor_fee_minor'])->toBe(0)
        ->and($openpay['platform_fee_minor'])->toBe(2000)
        ->and($openpay['photographer_net_minor'])->toBe(10000 - 626 - 2000)
        ->and($manual['photographer_net_minor'])->toBe(8000);

    foreach ([$openpay, $stripe, $manual] as $split) {
        expect($split['processor_fee_minor'] + $split['platform_fee_minor'] + $split['photographer_net_minor'])->toBe(10000)
            ->and($split['processor_fee_estimated'])->toBeTrue();
    }
});

test('the portal estimate follows the default web gateway', function () {
    config(['finisher.payments.default_gateway' => 'openpay']);

    expect(app(PhotoFeeCalculator::class)->rules()['processor_fee_percent'])->toBe(2.9);
});

test('a paid photo order records the gateway, the estimate flag and the frozen split once', function () {
    $buyer = User::factory()->create();
    $photo = marketPhoto();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [$photo->uuid]);

    marketPay($order, 'openpay');
    app(MarkOrderPaid::class)->handle($order->fresh()); // replayed confirmation

    $sales = PhotoSale::query()->where('order_id', $order->id)->get();
    expect($sales)->toHaveCount(1);

    $sale = $sales->first();
    expect($sale->payment_provider)->toBe('openpay')
        ->and($sale->processor_fee_estimated)->toBeTrue()
        ->and($sale->processor_fee_minor)->toBe(626)
        ->and($sale->platform_fee_minor)->toBe(2000)
        ->and($sale->photographer_net_minor)->toBe(7374)
        ->and($sale->gross_minor)->toBe($sale->processor_fee_minor + $sale->platform_fee_minor + $sale->photographer_net_minor);
});

test('a manual (cash) payment records no card processing fee', function () {
    $buyer = User::factory()->create();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [marketPhoto()->uuid]);

    app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, $order->total_minor, User::factory()->create());

    $sale = PhotoSale::query()->where('order_id', $order->id)->firstOrFail();
    expect($sale->payment_provider)->toBe('manual')
        ->and($sale->processor_fee_minor)->toBe(0)
        ->and($sale->photographer_net_minor)->toBe(8000);
});

test('reconciling the real gateway fee never rewrites the photographer net', function () {
    $order = app(CheckoutEventPhotos::class)->handle(User::factory()->create(), [marketPhoto()->uuid]);
    marketPay($order, 'stripe');
    $sale = PhotoSale::query()->where('order_id', $order->id)->firstOrFail();

    $difference = $sale->reconcileProcessorFee(700);

    $sale->refresh();
    expect($difference)->toBe(700 - 766)
        ->and($sale->processor_fee_actual_minor)->toBe(700)
        ->and($sale->processor_fee_reconciled_at)->not->toBeNull()
        ->and($sale->processor_fee_minor)->toBe(766)
        ->and($sale->photographer_net_minor)->toBe(10000 - 766 - 2000);
});

test('checkout never charges a partial selection: every uuid is accounted for', function () {
    $buyer = User::factory()->create();
    $ok = marketPhoto();
    $unpublished = marketPhoto(attributes: ['status' => EventPhotoStatus::Review]);
    $suspended = marketPhoto(marketPhotographer(PhotographerStatus::Suspended));
    $deleted = marketPhoto();
    $deletedUuid = $deleted->uuid;
    $deleted->delete();

    try {
        app(CheckoutEventPhotos::class)->handle($buyer, [$ok->uuid, $unpublished->uuid, $suspended->uuid, $deletedUuid, 'no-es-uuid']);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        $removed = explode(',', $e->errors()['photos_removed'][0]);
        expect($removed)->toEqualCanonicalizing([$unpublished->uuid, $suspended->uuid, $deletedUuid, 'no-es-uuid'])
            ->and($e->errors()['photos'][0])->toContain('4 fotografías')
            ->and($e->errors()['photos'][0])->toContain('no se cobró nada');
    }

    expect(Order::query()->where('user_id', $buyer->id)->count())->toBe(0);
});

test('photos already bought are reported instead of charged again', function () {
    $buyer = User::factory()->create();
    $photo = marketPhoto();
    marketPay(app(CheckoutEventPhotos::class)->handle($buyer, [$photo->uuid]), 'openpay');
    $other = marketPhoto();

    expect(fn () => app(CheckoutEventPhotos::class)->handle($buyer, [$photo->uuid, $other->uuid]))
        ->toThrow(ValidationException::class, 'ya la compraste');
    expect(Order::query()->where('user_id', $buyer->id)->count())->toBe(1);
});

test('mixed currencies cannot be combined in one photo order', function () {
    expect(fn () => app(CheckoutEventPhotos::class)->handle(User::factory()->create(), [
        marketPhoto()->uuid,
        marketPhoto(attributes: ['currency' => 'USD'])->uuid,
    ]))->toThrow(ValidationException::class, 'misma moneda');
});

test('an unpaid photo order unlocks nothing', function () {
    $buyer = User::factory()->create();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [marketPhoto()->uuid]);

    expect(PhotoSale::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('only the buyer can download the original', function () {
    $buyer = User::factory()->create();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [marketPhoto()->uuid]);
    marketPay($order, 'openpay');
    $sale = PhotoSale::query()->where('order_id', $order->id)->firstOrFail();

    $this->actingAs(User::factory()->create())->get("/fotos/descargar/{$sale->uuid}")->assertNotFound();
    $this->actingAs($buyer)->get("/fotos/descargar/{$sale->uuid}")->assertOk()->assertDownload();

    expect($sale->fresh()->download_count)->toBe(1);
});

test('a missing original tells the buyer instead of failing silently', function () {
    $buyer = User::factory()->create();
    $photo = marketPhoto();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [$photo->uuid]);
    marketPay($order, 'openpay');
    Storage::disk('event_photo_originals')->delete($photo->original_path);
    $sale = PhotoSale::query()->where('order_id', $order->id)->firstOrFail();

    $this->actingAs($buyer)->from('/fotos')->get("/fotos/descargar/{$sale->uuid}")->assertRedirect('/fotos');
});

test('marking a payout only settles that photographer pending sales', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $mine = marketPhotographer();
    $theirs = marketPhotographer();
    marketPay(app(CheckoutEventPhotos::class)->handle(User::factory()->create(), [marketPhoto($mine)->uuid]), 'openpay');
    marketPay(app(CheckoutEventPhotos::class)->handle(User::factory()->create(), [marketPhoto($theirs)->uuid]), 'openpay');

    $this->actingAs($admin)->post("/admin/photographers/{$mine->uuid}/payouts")->assertRedirect();

    expect(PhotoSale::query()->where('photographer_profile_id', $mine->id)->value('payout_status'))->toBe('paid')
        ->and(PhotoSale::query()->where('photographer_profile_id', $theirs->id)->value('payout_status'))->toBe('pending');
});
