<?php

use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Photos\CheckoutEventPhotos;
use App\Enums\BillingPeriod;
use App\Enums\EventPhotoStatus;
use App\Enums\PaymentStatus;
use App\Enums\PhotographerStatus;
use App\Enums\ProductPriceType;
use App\Enums\StoryQuotaKind;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\LegacyStory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PhotoExpansionPack;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../Quotas/quota-helpers.php';

/*
| Fixtures for tests/Feature/StoryMedia — race data, videos, purchased
| photos, per-kind quotas, price steps, terms and manual grants. Amounts
| are test data only, never real tariffs.
*/

function storyMediaCount(LegacyStory $story, string $role): int
{
    return DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->where('role', $role)->count();
}

/**
 * A sellable pack of a kind with one price in force and optional steps
 * (from_units => amount_minor).
 *
 * @param  array<int, int>  $steps
 */
function kindPack(StoryQuotaKind $kind, int $units = 10, int $amountMinor = 5000, array $steps = [], BillingPeriod $period = BillingPeriod::OneTime, array $attributes = []): PhotoExpansionPack
{
    $pack = PhotoExpansionPack::create([
        'kind' => $kind, 'billing_period' => $period, 'name' => 'Pack '.$kind->value, 'photos' => $units, 'active' => true, ...$attributes,
    ]);

    $schedule = ProductPriceSchedule::create([
        'product_id' => Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->value('id'),
        'photo_expansion_pack_id' => $pack->id,
        'price_type' => ProductPriceType::Standard,
        'amount_minor' => $amountMinor,
        'currency' => 'MXN',
        'tax_included' => true,
        'starts_at' => now()->subDay(),
        'active' => true,
    ]);

    foreach ($steps as $from => $amount) {
        $schedule->steps()->create(['from_units' => $from, 'amount_minor' => $amount]);
    }

    return $pack->fresh();
}

/** Registers a confirmed payment for the order (what a verified webhook does). */
function payStoryOrder(Order $order): Order
{
    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'manual', 'method' => 'cash',
        'status' => PaymentStatus::Paid, 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
        'provider_reference' => 'ref_'.Str::random(6), 'paid_at' => now(),
    ]);

    return app(MarkOrderPaid::class)->handle($order);
}

/** A published event photo whose original is a real PNG on the private disk. */
function soldEventPhoto(): EventPhoto
{
    $profile = PhotographerProfile::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'display_name' => 'Foto '.Str::random(4),
        'slug' => 'foto-'.Str::lower(Str::random(8)),
        'status' => PhotographerStatus::Approved,
    ]);
    $uuid = (string) Str::uuid();
    Storage::disk('event_photo_originals')->put("originals/{$uuid}.png", pngBytes(64, 48));

    return EventPhoto::create([
        'uuid' => $uuid,
        'photographer_profile_id' => $profile->id,
        'event_edition_id' => EventEdition::factory()->create()->id,
        'original_path' => "originals/{$uuid}.png",
        'preview_path' => "previews/{$uuid}.jpg",
        'thumb_path' => "thumbs/{$uuid}.jpg",
        'width' => 64,
        'height' => 48,
        'price_minor' => 10000,
        'currency' => 'MXN',
        'status' => EventPhotoStatus::Published,
        'published_at' => now(),
    ]);
}

/** The buyer pays for one event photo: returns the sale that grants it. */
function purchasedPhotoSale(User $buyer): PhotoSale
{
    $photo = soldEventPhoto();
    $order = app(CheckoutEventPhotos::class)->handle($buyer, [$photo->uuid]);
    payStoryOrder($order);

    return PhotoSale::query()->where('order_id', $order->id)->sole();
}
