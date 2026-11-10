<?php

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Quotas\CheckoutPhotoExpansion;
use App\Actions\Quotas\GrantStoryCapacity;
use App\Console\Commands\SeedQuotaDefaults;
use App\Enums\BillingPeriod;
use App\Enums\StoryMediaRole;
use App\Enums\StoryQuotaKind;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\MediaEntitlement;
use App\Models\MediaFile;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoExpansionPriceStep;
use App\Models\PhotoQuotaRule;
use App\Models\ProductPriceSchedule;
use App\Models\User;
use App\Notifications\AthleteAlert;
use App\Notifications\PlanExpiryReminder;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\StoryPhotoQuota;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/story-media-helpers.php';

/*
| Admin-editable limits for every media kind: resolution chain, hard cap,
| price steps ("banderas") frozen at purchase, prepaid terms (expiry,
| renewal, reminders), manual grants and the admin endpoints.
*/

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

function quotaAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return $admin;
}

function audioUpload(string $name = 'voz.mp3'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'audio/mpeg');
}

/* ---------------- limits per kind ---------------- */

test('each kind resolves product → product type → global rule → config, value by value', function () {
    config(['legacy.stories.max_photos' => 7, 'legacy.stories.max_videos' => 1, 'legacy.stories.max_audios' => 1, 'legacy.stories.max_video_seconds' => 60, 'legacy.stories.max_audio_seconds' => 180]);
    [$story, , $tag] = ownedStory();
    $quota = app(StoryMediaQuota::class);
    $product = $tag->productVariant->product;

    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(7)
        ->and($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(1)
        ->and($quota->limitFor($story, StoryQuotaKind::Audio))->toBe(1)
        ->and($quota->maxSecondsFor($story, StoryQuotaKind::Videos))->toBe(60)
        ->and($quota->maxSecondsFor($story, StoryQuotaKind::Audio))->toBe(180)
        ->and($quota->maxSecondsFor($story, StoryQuotaKind::Photos))->toBeNull();

    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::GLOBAL_KEY, 'included_photos' => 10, 'included_videos' => 2, 'max_video_seconds' => 90]);
    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::typeKey($product->type), 'product_type' => $product->type, 'included_videos' => 3, 'included_audio' => 2]);
    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::productKey($product->id), 'product_id' => $product->id, 'included_photos' => 5, 'max_audio_seconds' => 240]);

    $story = $story->fresh();

    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(5)          // product
        ->and($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(3)       // type
        ->and($quota->limitFor($story, StoryQuotaKind::Audio))->toBe(2)        // type
        ->and($quota->maxSecondsFor($story, StoryQuotaKind::Videos))->toBe(90) // global
        ->and($quota->maxSecondsFor($story, StoryQuotaKind::Audio))->toBe(240) // product
        // The photo-only service keeps answering the same thing.
        ->and(app(StoryPhotoQuota::class)->limitFor($story))->toBe(5);
});

test('quotas are independent per kind and a lowered limit never deletes content', function () {
    config(['legacy.stories.max_photos' => 1, 'legacy.stories.max_videos' => 2, 'legacy.stories.max_audios' => 2]);
    [$story, $owner] = ownedStory();
    $media = app(ManageStoryMedia::class);

    $media->add($story, $owner, UploadedFile::fake()->image('a.jpg', 400, 300), StoryMediaRole::Photo);
    $media->add($story, $owner, UploadedFile::fake()->create('a.mp4', 200, 'video/mp4'), StoryMediaRole::Video);
    $media->add($story, $owner, UploadedFile::fake()->create('b.mp4', 200, 'video/mp4'), StoryMediaRole::Video);
    $media->add($story, $owner, audioUpload('uno.mp3'), StoryMediaRole::Audio);
    $media->add($story, $owner, audioUpload('dos.mp3'), StoryMediaRole::Audio);

    foreach ([[StoryMediaRole::Photo, UploadedFile::fake()->image('b.jpg', 400, 300)], [StoryMediaRole::Video, UploadedFile::fake()->create('c.mp4', 200, 'video/mp4')], [StoryMediaRole::Audio, audioUpload('tres.mp3')]] as [$role, $file]) {
        expect(fn () => $media->add($story, $owner, $file, $role))->toThrow(PhotoQuotaExceededException::class);
    }

    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::GLOBAL_KEY, 'included_videos' => 0, 'included_audio' => 0]);
    $summary = app(StoryMediaQuota::class)->summaryAll($story->fresh());

    expect(storyMediaCount($story, 'video'))->toBe(2)
        ->and(storyMediaCount($story, 'audio'))->toBe(2)
        ->and($summary['videos'])->toMatchArray(['limit' => 0, 'used' => 2, 'remaining' => 0])
        ->and($summary['audio'])->toMatchArray(['limit' => 0, 'used' => 2, 'remaining' => 0])
        ->and(MediaFile::query()->count())->toBe(5);
});

test('with a single voice message a new recording replaces the previous one', function () {
    config(['legacy.stories.max_audios' => 1]);
    [$story, $owner] = ownedStory();
    $media = app(ManageStoryMedia::class);

    $first = $media->add($story, $owner, audioUpload('uno.mp3'), StoryMediaRole::Audio);
    $second = $media->add($story, $owner, audioUpload('dos.mp3'), StoryMediaRole::Audio);

    expect(storyMediaCount($story, 'audio'))->toBe(1)
        ->and(MediaFile::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(MediaFile::query()->whereKey($second->id)->exists())->toBeTrue();
});

test('race at the last video slot: the loser is refused under the lock and leaves nothing', function () {
    config(['legacy.stories.max_videos' => 1]);
    [$story, $owner] = ownedStory();

    $injected = false;
    DB::beforeExecuting(function (string $sql) use (&$injected, $story, $owner) {
        if ($injected || ! str_contains($sql, 'from "legacy_stories"')) {
            return;
        }
        $injected = true;

        $competitor = MediaFile::create([
            'uuid' => (string) Str::uuid(), 'owner_user_id' => $owner->id, 'kind' => 'video', 'status' => 'ready',
            'visibility' => 'private', 'disk' => 'local', 'directory' => 'users/'.$owner->id.'/legacy', 'mime' => 'video/mp4',
            'extension' => 'mp4', 'size_bytes' => 10, 'checksum' => hash('sha256', 'competitor'),
        ]);
        DB::table('legacy_story_media')->insert([
            'legacy_story_id' => $story->id, 'media_file_id' => $competitor->id, 'role' => 'video', 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    expect(fn () => app(ManageStoryMedia::class)->add($story, $owner, UploadedFile::fake()->create('late.mp4', 200, 'video/mp4'), StoryMediaRole::Video))
        ->toThrow(PhotoQuotaExceededException::class);

    expect($injected)->toBeTrue()
        ->and(storyMediaCount($story, 'video'))->toBeLessThanOrEqual(1)
        ->and(Storage::disk('media_tmp')->allFiles('incoming'))->toBe([]);
});

test('a hard cap stops both the limit and the sale of more capacity', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();
    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::GLOBAL_KEY, 'cap_photos' => 20]);
    $pack = kindPack(StoryQuotaKind::Photos, units: 10);
    $quota = app(StoryMediaQuota::class);

    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story));
    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(15)
        ->and($quota->summary($story, StoryQuotaKind::Photos))->toMatchArray(['can_expand' => false, 'cap' => 20]);

    // 15 + 10 would pass the cap of 20: not offered, not sellable.
    expect(fn () => app(CheckoutPhotoExpansion::class)->handle($owner, $story, $pack->id))->toThrow(ValidationException::class);

    // A manual grant beyond the cap is clamped by it.
    app(GrantStoryCapacity::class)->handle(quotaAdmin(), $owner, $story, StoryQuotaKind::Photos, 50, 'Cortesía');
    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(20);
});

/* ---------------- price steps ---------------- */

test('price steps: the band is chosen by what the story already holds, at its exact boundaries', function () {
    config(['legacy.stories.max_photos' => 10]);
    [$story, $owner] = ownedStory();
    // Hasta 20: $50 · de 21 a 50: $80 · más de 50: $120
    $pack = kindPack(StoryQuotaKind::Photos, units: 10, amountMinor: 5000, steps: [21 => 8000, 51 => 12000]);
    $quota = app(StoryMediaQuota::class);
    $grant = fn (int $units) => MediaEntitlement::create(['user_id' => $owner->id, 'legacy_story_id' => $story->id, 'extra_images' => $units, 'source' => MediaEntitlement::SOURCE_MANUAL_GRANT]);

    $price = $quota->resolvePrice($pack, $story);
    expect($price->amountMinor)->toBe(5000)->and($price->heldUnits)->toBe(10)->and($price->fromUnits)->toBe(0)->and($price->toUnits)->toBe(20)->and($price->step)->toBeNull();

    $grant(10); // holds 20 → still the first band
    expect($quota->resolvePrice($pack, $story)->amountMinor)->toBe(5000);

    $grant(1); // holds 21 → second band
    $price = $quota->resolvePrice($pack, $story);
    expect($price->amountMinor)->toBe(8000)->and($price->fromUnits)->toBe(21)->and($price->toUnits)->toBe(50);

    $grant(29); // holds 50 → still the second band
    expect($quota->resolvePrice($pack, $story)->amountMinor)->toBe(8000);

    $grant(1); // holds 51 → last band, open-ended
    $price = $quota->resolvePrice($pack, $story);
    expect($price->amountMinor)->toBe(12000)->and($price->toUnits)->toBeNull();

    expect($quota->summary($story, StoryQuotaKind::Photos)['expansion'])->toMatchArray([
        'id' => $pack->id, 'kind' => 'photos', 'units' => 10, 'price' => '120.00', 'currency' => 'MXN',
        'billing_period' => 'one_time', 'price_label' => '$120.00 MXN · pago único',
        'step' => ['id' => PhotoExpansionPriceStep::query()->where('from_units', 51)->value('id'), 'from' => 51, 'to' => null, 'held' => 51],
    ]);
});

test('held units also count stored items above a lowered limit', function () {
    config(['legacy.stories.max_photos' => 2]);
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 5, amountMinor: 1000, steps: [3 => 3000]);
    $media = app(ManageStoryMedia::class);

    foreach (['a', 'b'] as $name) {
        $media->add($story, $owner, UploadedFile::fake()->image("{$name}.jpg", 400, 300), StoryMediaRole::Photo);
    }
    DB::table('legacy_story_media')->insert(['legacy_story_id' => $story->id, 'media_file_id' => MediaFile::create([
        'uuid' => (string) Str::uuid(), 'owner_user_id' => $owner->id, 'kind' => 'image', 'status' => 'ready', 'visibility' => 'private',
        'disk' => 'local', 'directory' => 'x', 'mime' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1, 'checksum' => hash('sha256', 'x'),
    ])->id, 'role' => 'photo', 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()]);

    // Limit 2 but 3 stored: deleting nothing never buys a cheaper band.
    expect(app(StoryMediaQuota::class)->resolvePrice($pack, $story)->amountMinor)->toBe(3000);
});

test('the resolved step is frozen on the order item and survives later tariff changes', function () {
    config(['legacy.stories.max_photos' => 25]);
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 10, amountMinor: 5000, steps: [21 => 8000, 51 => 12000]);
    $step = PhotoExpansionPriceStep::query()->where('from_units', 21)->sole();

    $order = app(CheckoutPhotoExpansion::class)->handle($owner, $story);
    $item = $order->items()->sole();

    expect($order->total_minor)->toBe(8000)
        ->and($item->unit_price_minor)->toBe(8000)
        ->and($item->metadata)->toMatchArray([
            'expansion_kind' => 'photos', 'units' => 10, 'billing_period' => 'one_time',
            'price_step_id' => $step->id, 'step_from_units' => 21, 'step_to_units' => 50, 'held_units' => 25,
            'product_price_schedule_id' => $step->product_price_schedule_id,
        ]);

    // A new tariff starts before the customer pays: the order keeps its price.
    $pack->prices()->update(['ends_at' => now()]);
    ProductPriceSchedule::create([
        'product_id' => $item->product_id, 'photo_expansion_pack_id' => $pack->id, 'price_type' => 'standard',
        'amount_minor' => 99900, 'currency' => 'MXN', 'tax_included' => true, 'starts_at' => now(), 'active' => true,
    ]);

    $paid = payStoryOrder($order);
    $entitlement = MediaEntitlement::query()->sole();

    expect($paid->total_minor)->toBe(8000)
        ->and($entitlement->extra_images)->toBe(10)
        ->and($entitlement->product_price_schedule_id)->toBe($step->product_price_schedule_id)
        ->and(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Photos))->toBe(35)
        // The next purchase is priced by the new tariff.
        ->and(app(StoryMediaQuota::class)->resolvePrice($pack->fresh(), $story)->amountMinor)->toBe(99900);
});

test('video and audio packs are bought through the same order flow and grant their own kind', function () {
    config(['legacy.stories.max_photos' => 5, 'legacy.stories.max_videos' => 1, 'legacy.stories.max_audios' => 1]);
    [$story, $owner] = ownedStory();
    $videos = kindPack(StoryQuotaKind::Videos, units: 3, amountMinor: 14900);
    kindPack(StoryQuotaKind::Audio, units: 2, amountMinor: 3900);

    $this->actingAs($owner)->post(route('my-legacy.expansion.store', $story->public_id), ['kind' => 'videos'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $order = $owner->orders()->latest('id')->firstOrFail();

    expect($order->items()->sole()->name)->toBe('Pack videos · +3 videos')
        ->and($order->items()->sole()->metadata['expansion_kind'])->toBe('videos');

    payStoryOrder($order);
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, kind: StoryQuotaKind::Audio));
    $quota = app(StoryMediaQuota::class);

    expect($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(4)
        ->and($quota->limitFor($story, StoryQuotaKind::Audio))->toBe(3)
        ->and($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(5)
        ->and(MediaEntitlement::query()->where('photo_expansion_pack_id', $videos->id)->sole()->extra_videos)->toBe(3);

    // Somebody else cannot buy space for this story.
    $this->actingAs(User::factory()->create())->post(route('my-legacy.expansion.store', $story->public_id), ['kind' => 'videos'])->assertNotFound();
});

/* ---------------- prepaid terms ---------------- */

test('a term pack grants dated capacity; when it expires the space stops counting and nothing is deleted', function () {
    config(['legacy.stories.max_photos' => 2]);
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 3, amountMinor: 9900, period: BillingPeriod::Annual);
    $quota = app(StoryMediaQuota::class);
    $media = app(ManageStoryMedia::class);

    expect($quota->summary($story, StoryQuotaKind::Photos)['expansion'])->toMatchArray(['billing_period' => 'annual', 'months' => 12, 'price_label' => '$99.00 MXN por 12 meses'])
        ->and($quota->planFor($story, StoryQuotaKind::Photos))->toBeNull();

    $this->freezeSecond();
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story));
    $term = MediaEntitlement::query()->sole();

    expect($term->billing_period)->toBe(BillingPeriod::Annual)
        ->and($term->starts_at->equalTo(now()))->toBeTrue()
        ->and($term->expires_at->equalTo(now()->addMonthsNoOverflow(12)))->toBeTrue()
        ->and($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(5)
        ->and($quota->planFor($story, StoryQuotaKind::Photos))->toMatchArray(['billing_period' => 'annual', 'expired' => false, 'renewable' => false, 'pack_id' => $pack->id]);

    foreach (range(1, 5) as $n) {
        $media->add($story, $owner, UploadedFile::fake()->image("{$n}.jpg", 400, 300), StoryMediaRole::Photo);
    }

    // Changing the pack's period later never rewrites what was sold.
    $pack->update(['billing_period' => BillingPeriod::OneTime, 'photos' => 99]);
    expect($term->fresh()->billing_period)->toBe(BillingPeriod::Annual)->and($term->fresh()->extra_images)->toBe(3);
    $pack->update(['billing_period' => BillingPeriod::Annual, 'photos' => 3]);

    $this->travelTo(now()->addMonthsNoOverflow(12)->addMinute());

    $summary = $quota->summary($story->fresh(), StoryQuotaKind::Photos);
    expect($summary)->toMatchArray(['limit' => 2, 'used' => 5, 'remaining' => 0, 'can_expand' => true])
        ->and($summary['plan'])->toMatchArray(['expired' => true, 'renewable' => true, 'billing_period' => 'annual', 'pack_id' => $pack->id])
        ->and(storyMediaCount($story, 'photo'))->toBe(5)
        ->and(MediaFile::query()->count())->toBe(5);

    try {
        $media->add($story, $owner, UploadedFile::fake()->image('6.jpg', 400, 300), StoryMediaRole::Photo);
        $this->fail('The upload must be refused after the term ended.');
    } catch (PhotoQuotaExceededException $e) {
        expect($e->getMessage())->toContain('Tu plan venció')
            ->and($e->details()['plan']['expired'])->toBeTrue()
            ->and($e->details()['kind'])->toBe('photos');
    }

    // Renewing after the end starts a new term now.
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, $pack->id));
    $renewal = MediaEntitlement::query()->latest('id')->first();

    expect($renewal->starts_at->equalTo(now()))->toBeTrue()
        ->and($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(5)
        ->and($quota->planFor($story, StoryQuotaKind::Photos)['expired'])->toBeFalse();
});

test('renewing inside the window extends from the current end; far from the end it stacks', function () {
    config(['legacy.stories.max_videos' => 1]);
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Videos, units: 3, amountMinor: 14900, period: BillingPeriod::Semiannual);
    $quota = app(StoryMediaQuota::class);
    $this->freezeSecond();
    $start = now()->copy();

    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, kind: StoryQuotaKind::Videos));
    $first = MediaEntitlement::query()->sole();
    expect($first->expires_at->equalTo($start->copy()->addMonthsNoOverflow(6)))->toBeTrue();

    // 20 days before the end: offered for renewal, and paying extends it.
    $this->travelTo($first->expires_at->copy()->subDays(20));
    expect($quota->planFor($story, StoryQuotaKind::Videos))->toMatchArray(['renewable' => true, 'expired' => false, 'expires_in_days' => 20]);

    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, $pack->id));
    $renewal = MediaEntitlement::query()->latest('id')->first();

    expect($renewal->starts_at->equalTo($first->expires_at))->toBeTrue()
        ->and($renewal->expires_at->equalTo($first->expires_at->copy()->addMonthsNoOverflow(6)))->toBeTrue()
        // Not doubled while the first term runs…
        ->and($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(4)
        // …and the plan now shows the renewed end, no longer "renew".
        ->and($quota->planFor($story, StoryQuotaKind::Videos))->toMatchArray(['renewable' => false, 'expired' => false]);

    // After the first term ends the renewal carries on without a gap.
    $this->travelTo($first->expires_at->copy()->addDay());
    expect($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(4);

    // Buying again far from the end is extra capacity, starting now.
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, $pack->id));
    expect($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(7)
        ->and(MediaEntitlement::query()->latest('id')->first()->starts_at->equalTo(now()))->toBeTrue();
});

test('expiry reminders go out once per stage — 30, 7, 1 days and expired — with push and e-mail', function () {
    Notification::fake();
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 20, period: BillingPeriod::Annual);
    $this->freezeSecond();
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story));
    $term = MediaEntitlement::query()->sole();
    $end = $term->expires_at->copy();

    $run = function () {
        $this->artisan('quotas:remind-expiring')->assertSuccessful();
    };

    $this->travelTo($end->copy()->subDays(45));
    $run();
    Notification::assertNotSentTo($owner, PlanExpiryReminder::class);

    $this->travelTo($end->copy()->subDays(30));
    $run();
    $run(); // same day again: nothing new
    Notification::assertSentToTimes($owner, PlanExpiryReminder::class, 1);
    Notification::assertSentTo($owner, AthleteAlert::class, fn (AthleteAlert $alert) => str_contains($alert->title, '30 días'));

    $this->travelTo($end->copy()->subDays(7));
    $run();
    $this->travelTo($end->copy()->subDay());
    $run();
    Notification::assertSentToTimes($owner, PlanExpiryReminder::class, 3);
    Notification::assertSentTo($owner, AthleteAlert::class, fn (AthleteAlert $alert) => $alert->title === 'Tu plan vence mañana');

    $this->travelTo($end->copy()->addHour());
    $run();
    $run();
    Notification::assertSentToTimes($owner, PlanExpiryReminder::class, 4);
    Notification::assertSentTo($owner, PlanExpiryReminder::class, fn (PlanExpiryReminder $mail) => $mail->expired
        && ! str_contains(strtolower($mail->body.$mail->subject), 'openpay')
        && str_contains($mail->toMail($owner)->render()->toHtml(), 'no se borra'));

    expect($term->fresh()->expiry_reminders)->toContain('30', '7', '1', 'expired');
});

test('a renewed term and a late run never send stale reminders', function () {
    Notification::fake();
    [$story, $owner] = ownedStory();
    $pack = kindPack(StoryQuotaKind::Photos, units: 20, period: BillingPeriod::Annual);
    $this->freezeSecond();
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story));
    $term = MediaEntitlement::query()->sole();

    // The command did not run for weeks: only the closest stage is sent.
    $this->travelTo($term->expires_at->copy()->subDays(5));
    $this->artisan('quotas:remind-expiring')->assertSuccessful();
    Notification::assertSentToTimes($owner, PlanExpiryReminder::class, 1);
    expect($term->fresh()->expiry_reminders)->toContain('7', '30')->not->toContain('1');

    // Renewed: no "vence mañana", no "venció".
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story, $pack->id));
    $this->travelTo($term->expires_at->copy()->subHours(12));
    $this->artisan('quotas:remind-expiring')->assertSuccessful();
    $this->travelTo($term->expires_at->copy()->addHour());
    $this->artisan('quotas:remind-expiring')->assertSuccessful();

    Notification::assertSentToTimes($owner, PlanExpiryReminder::class, 1);
});

/* ---------------- manual grants ---------------- */

test('an admin grants capacity to a story or to a person, with a reason, and can revoke it', function () {
    config(['legacy.stories.max_photos' => 5, 'legacy.stories.max_videos' => 1]);
    $admin = quotaAdmin();
    [$story, $owner] = ownedStory();
    [$second] = ownedStory(owner: $owner);
    [$foreign] = ownedStory();
    $quota = app(StoryMediaQuota::class);

    $this->actingAs($admin)->post(route('admin.quotas.grants.store'), [
        'target' => 'story', 'story' => url('/legacy/'.$story->public_id), 'kind' => 'photos', 'units' => 15, 'reason' => 'Compensación por una falla',
    ])->assertSessionHasNoErrors();

    $this->post(route('admin.quotas.grants.store'), [
        'target' => 'user', 'email' => $owner->email, 'kind' => 'videos', 'units' => 2, 'reason' => 'Embajador',
    ])->assertSessionHasNoErrors();

    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(20)
        ->and($quota->limitFor($second, StoryQuotaKind::Photos))->toBe(5)
        // The account-wide grant counts on every story of that person…
        ->and($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(3)
        ->and($quota->limitFor($second, StoryQuotaKind::Videos))->toBe(3)
        // …and on nobody else's.
        ->and($quota->limitFor($foreign, StoryQuotaKind::Videos))->toBe(1);

    $grant = MediaEntitlement::query()->where('legacy_story_id', $story->id)->sole();
    expect($grant->source)->toBe(MediaEntitlement::SOURCE_MANUAL_GRANT)
        ->and($grant->granted_by)->toBe($admin->id)
        ->and($grant->grant_reason)->toBe('Compensación por una falla')
        ->and(Activity::query()->where('log_name', 'quotas')->where('subject_id', $grant->id)->where('causer_id', $admin->id)->exists())->toBeTrue();

    // Shown in the entitlements table of the admin page.
    $this->get(route('admin.quotas.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('entitlements.0.source', 'manual')
        ->where('entitlements.0.account_wide', true)
        ->where('entitlements.0.revocable', true)
        ->where('entitlements.1.grant_reason', 'Compensación por una falla')
        ->where('entitlements.1.units', 15)
        ->where('stats.manual_grants', 2));

    // Validation: a reason is mandatory, the target must exist.
    $this->post(route('admin.quotas.grants.store'), ['target' => 'story', 'story' => $story->public_id, 'kind' => 'photos', 'units' => 5])->assertSessionHasErrors('reason');
    $this->post(route('admin.quotas.grants.store'), ['target' => 'story', 'story' => 'noexiste99', 'kind' => 'photos', 'units' => 5, 'reason' => 'x y z'])->assertSessionHasErrors('story');
    $this->post(route('admin.quotas.grants.store'), ['target' => 'user', 'email' => 'nadie@example.com', 'kind' => 'photos', 'units' => 5, 'reason' => 'x y z'])->assertSessionHasErrors('email');

    // Revoking lowers the limit and deletes nothing.
    app(ManageStoryMedia::class)->add($story, $owner, UploadedFile::fake()->image('a.jpg', 400, 300), StoryMediaRole::Photo);
    $this->post(route('admin.quotas.grants.revoke', $grant->id), ['reason' => 'Se otorgó por error'])->assertSessionHasNoErrors();

    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(5)
        ->and($grant->fresh()->revoked_at)->not->toBeNull()
        ->and($grant->fresh()->revoked_by)->toBe($admin->id)
        ->and($grant->fresh()->revocation_reason)->toBe('Se otorgó por error')
        ->and(storyMediaCount($story, 'photo'))->toBe(1);

    // A purchase is not revocable from here; a customer cannot grant.
    kindPack(StoryQuotaKind::Photos);
    payStoryOrder(app(CheckoutPhotoExpansion::class)->handle($owner, $story));
    $purchase = MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->sole();
    $this->post(route('admin.quotas.grants.revoke', $purchase->id))->assertSessionHasErrors('entitlement');
    expect($purchase->fresh()->revoked_at)->toBeNull();

    $this->actingAs($owner)->post(route('admin.quotas.grants.store'), ['target' => 'story', 'story' => $story->public_id, 'kind' => 'photos', 'units' => 99, 'reason' => 'para mí'])->assertForbidden();
});

test('a dated manual grant stops counting when it ends', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();

    app(GrantStoryCapacity::class)->handle(quotaAdmin(), $owner, $story, StoryQuotaKind::Photos, 10, 'Promoción', now()->addDays(10));
    expect(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Photos))->toBe(15);

    $this->travelTo(now()->addDays(11));
    expect(app(StoryMediaQuota::class)->limitFor($story, StoryQuotaKind::Photos))->toBe(5);
});

/* ---------------- admin configuration ---------------- */

test('admin edits limits per scope and kind; packs carry kind and billing period; prices carry steps', function () {
    $admin = quotaAdmin();
    [$story] = ownedStory();

    $this->actingAs($admin)->post(route('admin.quotas.rules.store'), [
        'scope' => 'global', 'included_photos' => 10, 'included_videos' => 1, 'included_audio' => 1,
        'max_video_seconds' => 60, 'max_audio_seconds' => 180, 'cap_photos' => 200,
    ])->assertSessionHasNoErrors();
    $this->post(route('admin.quotas.rules.store'), ['scope' => 'type', 'product_type' => 'legacy_tag', 'included_videos' => 2])->assertSessionHasNoErrors();

    $quota = app(StoryMediaQuota::class);
    expect($quota->limitFor($story, StoryQuotaKind::Photos))->toBe(10)
        ->and($quota->limitFor($story, StoryQuotaKind::Videos))->toBe(2)
        ->and($quota->capFor($story, StoryQuotaKind::Photos))->toBe(200);

    $this->post(route('admin.quotas.rules.store'), ['scope' => 'global'])->assertSessionHasErrors('included_photos');
    $this->post(route('admin.quotas.rules.store'), ['scope' => 'global', 'included_photos' => 50, 'cap_photos' => 10])->assertSessionHasErrors('cap_photos');

    $this->post(route('admin.quotas.packs.store'), [
        'kind' => 'videos', 'billing_period' => 'annual', 'name' => '+3 videos', 'photos' => 3, 'scope' => 'global', 'active' => true,
        'price' => ['amount' => '149.00', 'currency' => 'MXN', 'tax_included' => true, 'steps' => [
            ['from_units' => 7, 'amount' => '199.00', 'apple_product_id' => 'com.fl.videos3.b', 'google_product_id' => 'videos_3_b'],
        ]],
    ])->assertSessionHasNoErrors();

    $pack = PhotoExpansionPack::query()->sole();
    $schedule = $pack->prices()->sole();

    expect($pack->kind)->toBe(StoryQuotaKind::Videos)
        ->and($pack->billing_period)->toBe(BillingPeriod::Annual)
        ->and($schedule->amount_minor)->toBe(14900)
        ->and($schedule->steps()->sole()->only(['from_units', 'amount_minor', 'apple_product_id']))->toBe(['from_units' => 7, 'amount_minor' => 19900, 'apple_product_id' => 'com.fl.videos3.b']);

    // A new price is a new row with its own steps; the old one is only closed.
    // The same store products may repeat on the next price of THIS pack.
    $this->travel(1)->minutes();
    $this->post(route('admin.quotas.prices.store', $pack->id), [
        'amount' => '169.00', 'currency' => 'MXN', 'tax_included' => true,
        'steps' => [['from_units' => 7, 'amount' => '219.00', 'apple_product_id' => 'com.fl.videos3.b'], ['from_units' => 13, 'amount' => '259.00']],
    ])->assertSessionHasNoErrors();

    expect($pack->prices()->count())->toBe(2)
        ->and($schedule->fresh()->amount_minor)->toBe(14900)
        ->and($schedule->fresh()->ends_at)->not->toBeNull()
        ->and($schedule->steps()->sole()->amount_minor)->toBe(19900)
        ->and($pack->priceAt()->steps()->pluck('amount_minor', 'from_units')->all())->toBe([7 => 21900, 13 => 25900]);

    // Steps must start at distinct amounts ≥ 1; another pack cannot take this pack's store product.
    $this->post(route('admin.quotas.prices.store', $pack->id), ['amount' => '1', 'currency' => 'MXN', 'steps' => [['from_units' => 5, 'amount' => '2'], ['from_units' => 5, 'amount' => '3']]])->assertSessionHasErrors();
    $other = kindPack(StoryQuotaKind::Photos);
    $this->travel(1)->minutes();
    $this->post(route('admin.quotas.prices.store', $other->id), ['amount' => '10', 'currency' => 'MXN', 'steps' => [['from_units' => 5, 'amount' => '20', 'apple_product_id' => 'com.fl.videos3.b']]])
        ->assertSessionHasErrors('steps.0.apple_product_id');

    // The kind of a pack with prices cannot change; its period can (future sales only).
    $this->patch(route('admin.quotas.packs.update', $pack->id), ['kind' => 'photos', 'billing_period' => 'annual', 'name' => '+3 videos', 'photos' => 3, 'scope' => 'global'])->assertSessionHasErrors('kind');
    $this->patch(route('admin.quotas.packs.update', $pack->id), ['kind' => 'videos', 'billing_period' => 'semiannual', 'name' => '+3 videos', 'photos' => 3, 'scope' => 'global'])->assertSessionHasNoErrors();
    expect($pack->fresh()->billing_period)->toBe(BillingPeriod::Semiannual);

    $this->get(route('admin.quotas.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/quotas/Index')
        ->where('globalRule.included_photos', 10)
        ->where('globalRule.cap_photos', 200)
        ->has('kinds', 3)
        ->has('billingPeriods', 3)
        ->where('packs.1.kind', 'videos')
        ->where('packs.1.billing_period', 'semiannual')
        ->where('packs.1.current_price.steps.0.from_units', 7)
        ->where('packs.1.current_price.steps.1.amount', '259.00'));
});

test('the owner editor receives every quota, the race data and the purchase options', function () {
    [$story, $owner] = ownedStory();
    kindPack(StoryQuotaKind::Videos, units: 3, amountMinor: 14900, period: BillingPeriod::Annual);

    $this->actingAs($owner)->get(route('my-legacy.edit', $story->public_id))->assertOk()->assertInertia(fn ($page) => $page
        ->component('my-legacy/Edit')
        ->has('quota.photos.limit')
        ->where('quota.videos.can_expand', true)
        ->where('quota.videos.expansion.price_label', '$149.00 MXN por 12 meses')
        ->where('quota.audio.max_seconds', 180)
        ->has('raceData.official')
        ->has('finishStatuses', 4)
        ->has('fields.bib_number')
        ->where('hasPurchasedPhotos', false));
});

/* ---------------- defaults ---------------- */

test('quotas:seed-defaults creates the starting limits and packs once and never overwrites', function () {
    $this->artisan('quotas:seed-defaults')->assertSuccessful();

    $global = PhotoQuotaRule::query()->where('scope_key', PhotoQuotaRule::GLOBAL_KEY)->sole();
    expect($global->included_photos)->toBe(10)
        ->and($global->included_videos)->toBe(1)
        ->and($global->max_video_seconds)->toBe(60)
        ->and($global->max_audio_seconds)->toBe(180)
        ->and(PhotoExpansionPack::query()->count())->toBe(count(SeedQuotaDefaults::packs()))
        ->and(PhotoExpansionPack::query()->where('kind', 'photos')->where('billing_period', 'annual')->sole()->priceAt()->steps()->count())->toBe(2);

    // The owner changes things in admin…
    $global->update(['included_photos' => 3]);
    $pack = PhotoExpansionPack::query()->where('name', '+10 fotos')->sole();
    $pack->update(['photos' => 12, 'active' => false]);
    $prices = ProductPriceSchedule::query()->count();

    // …and running it again touches nothing.
    $this->artisan('quotas:seed-defaults')->assertSuccessful();

    expect($global->fresh()->included_photos)->toBe(3)
        ->and($pack->fresh()->photos)->toBe(12)
        ->and($pack->fresh()->active)->toBeFalse()
        ->and(PhotoExpansionPack::query()->count())->toBe(count(SeedQuotaDefaults::packs()))
        ->and(ProductPriceSchedule::query()->count())->toBe($prices);

    // No customer-facing text says it is an example.
    expect(PhotoExpansionPack::query()->pluck('description')->implode(' '))->not->toContain('jemplo');
});
