<?php

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Quotas\CheckoutPhotoExpansion;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoryMediaRole;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PhotoExpansionPack;
use App\Models\ProductPriceSchedule;
use App\Models\User;
use App\Services\Media\StoryPhotoQuota;
use App\Support\Commerce\PaymentWebhookOutcome;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/quota-helpers.php';

function pendingPaymentFor(Order $order, string $reference): Payment
{
    return Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'openpay', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
        'provider_reference' => $reference,
    ]);
}

test('checkout creates a pending order with the price frozen and grants nothing yet', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();
    $pack = expansionPack(photos: 10, amountMinor: 5000, currency: 'USD');

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    $item = $order->items()->sole();

    expect($order->payment_status)->toBe(OrderPaymentStatus::Pending)
        ->and($order->total_minor)->toBe(5000)
        ->and($order->currency)->toBe('USD')
        ->and($item->metadata['kind'])->toBe('photo_expansion')
        ->and($item->metadata['legacy_story_id'])->toBe($story->id)
        ->and($item->metadata['photo_expansion_pack_id'])->toBe($pack->id)
        ->and(MediaEntitlement::query()->count())->toBe(0)
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(5);
});

test('the web purchase route redirects to the order page and never grants on its own', function () {
    [$story, $owner] = ownedStory();
    expansionPack();

    $response = $this->actingAs($owner)->post(route('my-legacy.photo-expansion.store', $story->public_id));

    $order = Order::query()->sole();
    $response->assertRedirect(route('store.orders.show', $order));
    expect(MediaEntitlement::query()->count())->toBe(0);
});

test('a stranger cannot buy an expansion for someone else\'s story', function () {
    [$story] = ownedStory();
    expansionPack();

    $this->actingAs(User::factory()->create())->post(route('my-legacy.photo-expansion.store', $story->public_id))->assertNotFound();
    expect(Order::query()->count())->toBe(0);
});

test('the entitlement is granted only once the payment webhook confirms it, and only once', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();
    expansionPack(photos: 10, amountMinor: 5000, currency: 'USD');

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    pendingPaymentFor($order, 'tr_exp_1');

    // A failed attempt grants nothing.
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_fail', 'tr_exp_1', PaymentStatus::Failed, 5000, 'USD'));
    expect(MediaEntitlement::query()->count())->toBe(0);

    $paid = new PaymentWebhookOutcome('openpay', 'evt_paid', 'tr_exp_1', PaymentStatus::Paid, 5000, 'USD');
    app(ProcessPaymentWebhook::class)->handle($paid);
    app(ProcessPaymentWebhook::class)->handle($paid); // replay

    $entitlement = MediaEntitlement::query()->sole();
    expect($entitlement->legacy_story_id)->toBe($story->id)
        ->and($entitlement->extra_images)->toBe(10)
        ->and($entitlement->source)->toBe('photo_expansion')
        ->and($entitlement->order_item_id)->toBe($order->items()->value('id'))
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15)
        ->and($order->items()->value('fulfilled_at'))->not->toBeNull();
});

test('a second checkout while one is pending points to the existing order', function () {
    [$story, $owner] = ownedStory();
    expansionPack();

    app(CheckoutPhotoExpansion::class)->handle($owner, $story);

    expect(fn () => app(CheckoutPhotoExpansion::class)->handle($owner, $story))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(1);
});

test('a new price never mutates the past one and past purchases keep their price', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    [$story, $owner] = ownedStory();

    $pack = expansionPack(photos: 10, amountMinor: 5000, currency: 'USD');
    $first = ProductPriceSchedule::query()->where('photo_expansion_pack_id', $pack->id)->sole();

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    pendingPaymentFor($order, 'tr_exp_2');
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_2', 'tr_exp_2', PaymentStatus::Paid, 5000, 'USD'));

    $this->actingAs($admin)->post(route('admin.quotas.prices.store', $pack->id), [
        'amount' => '70.00', 'currency' => 'USD', 'tax_included' => true,
    ])->assertSessionHasNoErrors();

    $first->refresh();
    $prices = ProductPriceSchedule::query()->where('photo_expansion_pack_id', $pack->id)->orderBy('id')->get();

    expect($prices)->toHaveCount(2)
        ->and($first->amount_minor)->toBe(5000)
        ->and($first->currency)->toBe('USD')
        ->and($first->ends_at)->not->toBeNull()
        ->and($prices->last()->amount_minor)->toBe(7000)
        ->and($order->items()->value('unit_price_minor'))->toBe(5000)
        ->and(MediaEntitlement::query()->sole()->product_price_schedule_id)->toBe($first->id)
        ->and(app(StoryPhotoQuota::class)->summary($story)['expansion']['price'])->toBe('70.00');

    // A price that already started (and sold) cannot be cancelled.
    $this->post(route('admin.quotas.prices.cancel', $first->id))->assertSessionHasErrors('schedule');
    expect($first->fresh()->active)->toBeTrue();
});

test('a pack with prices or sales is archived, never deleted', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $pack = expansionPack();

    $this->actingAs($admin)->delete(route('admin.quotas.packs.destroy', $pack->id));
    expect(PhotoExpansionPack::query()->find($pack->id))->not->toBeNull();

    $this->post(route('admin.quotas.packs.archive', $pack->id));
    expect($pack->fresh()->archived_at)->not->toBeNull();
});

test('admin page is gated by quotas.manage', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    expansionPack();

    $this->actingAs($admin)->get(route('admin.quotas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/quotas/Index')->has('packs', 1)->has('types')->has('products'));

    $this->actingAs(User::factory()->create())->get(route('admin.quotas.index'))->assertForbidden();
});

test('API: owner reads the story quota, a stranger gets 404, entitlements are listed', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();
    expansionPack(photos: 10, amountMinor: 5000, currency: 'USD');

    $this->getJson("/api/v1/stories/{$story->public_id}/quota", apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.limit', 5)
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.remaining', 5)
        ->assertJsonPath('data.can_expand', true)
        ->assertJsonPath('data.expansion.price', '50.00')
        ->assertJsonPath('data.expansion.currency', 'USD');

    $this->getJson("/api/v1/stories/{$story->public_id}/quota", apiAuthHeader(User::factory()->create()))->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/stories/{$story->public_id}/quota")->assertUnauthorized();

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    pendingPaymentFor($order, 'tr_exp_3');
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_3', 'tr_exp_3', PaymentStatus::Paid, 5000, 'USD'));

    $this->getJson('/api/v1/me/entitlements', apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.story', $story->public_id)
        ->assertJsonPath('data.0.photos', 10)
        ->assertJsonPath('data.0.price', '50.00');
});

test('a full refund revokes the expansion right but never deletes uploaded photos', function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
    config(['legacy.stories.max_photos' => 1]);
    [$story, $owner] = ownedStory();
    expansionPack(photos: 2, amountMinor: 5000, currency: 'USD');

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    pendingPaymentFor($order, 'tr_exp_refund');
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_r_paid', 'tr_exp_refund', PaymentStatus::Paid, 5000, 'USD'));
    expect(app(StoryPhotoQuota::class)->limitFor($story))->toBe(3);

    $media = app(ManageStoryMedia::class);
    foreach (['a', 'b', 'c'] as $name) {
        $media->add($story, $owner, UploadedFile::fake()->image("{$name}.jpg", 400, 300), StoryMediaRole::Photo);
    }

    // A partial refund keeps the right; only a full refund revokes it.
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_r_partial', 'tr_exp_refund', PaymentStatus::PartiallyRefunded, 5000, 'USD', refundedMinor: 1000));
    expect(MediaEntitlement::query()->sole()->revoked_at)->toBeNull();

    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome('openpay', 'evt_r_full', 'tr_exp_refund', PaymentStatus::Refunded, 5000, 'USD', refundedMinor: 5000));

    $summary = app(StoryPhotoQuota::class)->summary($story);
    expect(MediaEntitlement::query()->sole()->revoked_at)->not->toBeNull()
        ->and($summary['limit'])->toBe(1)
        ->and($summary['used'])->toBe(3)
        ->and($summary['remaining'])->toBe(0)
        ->and(DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->count())->toBe(3);

    expect(fn () => $media->add($story, $owner, UploadedFile::fake()->image('d.jpg', 400, 300), StoryMediaRole::Photo))
        ->toThrow(PhotoQuotaExceededException::class);
});
