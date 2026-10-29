<?php

use App\Actions\LegacyTags\ActivateTag;
use App\Actions\LegacyTags\CreateLot;
use App\Actions\LegacyTags\ManageTag;
use App\Actions\LegacyTags\ReceiveLot;
use App\Actions\LegacyTags\RecordInPersonSale;
use App\Actions\LegacyTags\TransferTags;
use App\Enums\InventoryLocationType;
use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductPriceType;
use App\Enums\SalesChannel;
use App\Enums\TagAccessSource;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\Ally;
use App\Models\InventoryLevel;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\ProductPriceSchedule;
use App\Models\User;
use App\Support\LegacyTags\TagCode;

require_once __DIR__.'/helpers.php';

function onHand(int $variantId, int $locationId): int
{
    return (int) InventoryLevel::query()->where('product_variant_id', $variantId)->where('inventory_location_id', $locationId)->value('quantity_on_hand');
}

test('a lot generates unique random codes before manufacturing and counts stock only when received', function () {
    $staff = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();

    $lot = app(CreateLot::class)->handle($variant, 5, [], $staff);
    $tags = LegacyTag::query()->where('inventory_lot_id', $lot->id)->get();

    expect($tags)->toHaveCount(5)
        ->and($tags->pluck('public_code')->unique())->toHaveCount(5)
        ->and($tags->every(fn (LegacyTag $t) => $t->status === LegacyTagStatus::Manufactured))->toBeTrue()
        ->and($tags->every(fn (LegacyTag $t) => strlen($t->public_code) === 12 && strspn($t->public_code, TagCode::ALPHABET) === 12))->toBeTrue()
        ->and(onHand($variant->id, $warehouse->id))->toBe(0);

    $defective = $tags->first()->public_code;
    app(ReceiveLot::class)->handle($lot, $warehouse, $staff, [$defective]);

    expect($lot->fresh()->status)->toBe(InventoryLotStatus::Received)
        ->and($lot->fresh()->quantity_received)->toBe(4)
        ->and(onHand($variant->id, $warehouse->id))->toBe(4)
        ->and(LegacyTag::query()->where('public_code', $defective)->value('status'))->toBe(LegacyTagStatus::Disabled)
        ->and(fn () => app(ReceiveLot::class)->handle($lot->fresh(), $warehouse, $staff))->toThrow(InvalidArgumentException::class);
});

test('tag codes normalize from typed input and scanned urls', function () {
    expect(TagCode::normalize('abcd-efgh-jkmn'))->toBe('ABCDEFGHJKMN')
        ->and(TagCode::normalize('https://finisherlegacy.com/t/ABCDEFGHJKMN?src=qr'))->toBe('ABCDEFGHJKMN')
        ->and(TagCode::normalize('FL ABCD EFGH JKMN'))->toBe('ABCDEFGHJKMN')
        ->and(TagCode::normalize('ABCD-EFGH-JKM0'))->toBeNull()
        ->and(TagCode::normalize('short'))->toBeNull()
        ->and(TagCode::format('ABCDEFGHJKMN'))->toBe('ABCD-EFGH-JKMN');
});

test('transfer moves units and aggregate stock between locations with paired movements', function () {
    $staff = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $ally = Ally::factory()->create();
    $shop = tagLocation(InventoryLocationType::Ally, $ally);
    tagStock($variant, $warehouse, 10, $staff);

    $moved = app(TransferTags::class)->quantity($variant, $warehouse, $shop, 4, $staff, 'Reposición gimnasio');

    expect($moved)->toHaveCount(4)
        ->and(onHand($variant->id, $warehouse->id))->toBe(6)
        ->and(onHand($variant->id, $shop->id))->toBe(4)
        ->and($moved->every(fn (LegacyTag $t) => $t->inventory_location_id === $shop->id))->toBeTrue()
        ->and($moved->first()->events()->first()->type)->toBe(LegacyTagEventType::Transferred);

    expect(fn () => app(TransferTags::class)->quantity($variant, $warehouse, $shop, 50, $staff))
        ->toThrow(InvalidArgumentException::class);
});

test('an in-person sale is paid, fulfilled, keeps its channel and delivers the exact scanned pieces', function () {
    $seller = tagStaff('sales_staff');
    $variant = tagVariant(39900);
    $ally = Ally::factory()->create();
    $shop = tagLocation(InventoryLocationType::Ally, $ally);
    $tags = tagStock($variant, $shop, 3);

    // Ally price is a channel price schedule — never a constant in code.
    ProductPriceSchedule::create([
        'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'price_type' => ProductPriceType::Standard,
        'amount_minor' => 34900, 'currency' => 'MXN', 'active' => true, 'sales_channel' => SalesChannel::Ally->value, 'ally_id' => $ally->id,
    ]);

    $order = app(RecordInPersonSale::class)->handle($shop, $seller, PaymentMethod::Cash, [$tags[0]->displayCode(), 'https://finisherlegacy.com/t/'.$tags[1]->public_code.'?src=qr'], [], ['email' => 'corredor@example.com'], null, now()->subSeconds(40));

    expect($order->sales_channel)->toBe(SalesChannel::Ally)
        ->and($order->ally_id)->toBe($ally->id)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->total_minor)->toBe(69800)
        ->and($order->legacyTags)->toHaveCount(2)
        ->and($order->legacyTags->every(fn (LegacyTag $t) => $t->status === LegacyTagStatus::Delivered && $t->sales_channel === SalesChannel::Ally))->toBeTrue()
        ->and(onHand($variant->id, $shop->id))->toBe(1)
        ->and($tags[2]->fresh()->status)->toBe(LegacyTagStatus::InStock);

    // A piece already sold cannot be sold twice.
    expect(fn () => app(RecordInPersonSale::class)->handle($shop, $seller, PaymentMethod::Cash, [$tags[0]->public_code]))
        ->toThrow(InvalidArgumentException::class);
});

test('activation: new tag + user, idempotent for the owner, refused for anyone else', function () {
    $variant = tagVariant();
    $tag = LegacyTag::factory()->create(['product_variant_id' => $variant->id, 'status' => LegacyTagStatus::Delivered, 'sold_at' => now()]);
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $story = app(ActivateTag::class)->handle($tag, $owner, TagAccessSource::Nfc);
    $tag->refresh();

    expect($tag->status)->toBe(LegacyTagStatus::Activated)
        ->and($tag->owner_athlete_id)->toBe($owner->athlete->id)
        ->and($tag->ownedProduct)->not->toBeNull()
        ->and($story->legacy_tag_id)->toBe($tag->id)
        ->and($story->athlete_id)->toBe($owner->athlete->id);

    // Same user again (double tap / typed code after NFC): same story, no second one.
    $again = app(ActivateTag::class)->handle($tag, $owner, TagAccessSource::Code);
    expect($again->id)->toBe($story->id)->and(LegacyStory::query()->count())->toBe(1);

    expect(fn () => app(ActivateTag::class)->handle($tag, $other, TagAccessSource::Code))
        ->toThrow(TagAlreadyActivatedException::class);
    expect($tag->fresh()->owner_athlete_id)->toBe($owner->athlete->id);
});

test('activation refuses disabled, replaced and warehouse stock with a stable reason', function (LegacyTagStatus $status, string $reason) {
    $tag = LegacyTag::factory()->create(['status' => $status, 'inventory_location_id' => tagLocation()->id]);

    try {
        app(ActivateTag::class)->handle($tag, User::factory()->create());
        $this->fail('should not activate');
    } catch (TagNotActivatableException $e) {
        expect($e->reason)->toBe($reason);
    }

    expect($tag->fresh()->status)->toBe($status);
})->with([
    'disabled' => [LegacyTagStatus::Disabled, 'disabled'],
    'replaced' => [LegacyTagStatus::Replaced, 'replaced'],
    'warehouse stock' => [LegacyTagStatus::InStock, 'not_sold'],
    'manufactured' => [LegacyTagStatus::Manufactured, 'not_sold'],
]);

test('a piece sold at an ally but never registered is activated and the sale is inferred', function () {
    $variant = tagVariant();
    $ally = Ally::factory()->create();
    $shop = tagLocation(InventoryLocationType::Ally, $ally);
    $tag = tagStock($variant, $shop, 2)->first();

    app(ActivateTag::class)->handle($tag, User::factory()->create(), TagAccessSource::Qr);
    $tag->refresh();

    expect($tag->status)->toBe(LegacyTagStatus::Activated)
        ->and($tag->sales_channel)->toBe(SalesChannel::Ally)
        ->and($tag->ally_id)->toBe($ally->id)
        ->and($tag->metadata['unreported_sale'] ?? false)->toBeTrue()
        ->and(onHand($variant->id, $shop->id))->toBe(1);
});

test('two concurrent activations of the same tag leave exactly one owner', function () {
    $tag = LegacyTag::factory()->create(['status' => LegacyTagStatus::Delivered]);
    $a = User::factory()->create();
    $b = User::factory()->create();

    // Simulate the race: B's request starts after A committed (the lock +
    // row lock serialise them); B must never overwrite A.
    $results = collect([$a, $b])->map(function (User $u) use ($tag) {
        try {
            app(ActivateTag::class)->handle($tag, $u);

            return 'ok';
        } catch (TagAlreadyActivatedException) {
            return 'conflict';
        }
    });

    expect($results->all())->toBe(['ok', 'conflict'])
        ->and($tag->fresh()->owner_athlete_id)->toBe($a->athlete->id)
        ->and(LegacyStory::query()->count())->toBe(1);
});

test('replacing a defective piece moves owner and story to the new unit and keeps history', function () {
    $staff = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $stock = tagStock($variant, $warehouse, 2, $staff);
    $sold = LegacyTag::factory()->create(['product_variant_id' => $variant->id, 'status' => LegacyTagStatus::Delivered]);
    $owner = User::factory()->create();
    $story = app(ActivateTag::class)->handle($sold, $owner);

    $new = app(ManageTag::class)->replace($sold, $stock->first(), $staff, 'NFC no responde');

    expect($new->status)->toBe(LegacyTagStatus::Activated)
        ->and($new->owner_athlete_id)->toBe($owner->athlete->id)
        ->and($new->replaces_tag_id)->toBe($sold->id)
        ->and($sold->fresh()->status)->toBe(LegacyTagStatus::Replaced)
        ->and($story->fresh()->legacy_tag_id)->toBe($new->id)
        ->and(onHand($variant->id, $warehouse->id))->toBe(1)
        ->and($sold->events()->pluck('type')->map->value->all())->toContain('replaced', 'activated');
});

test('a return revokes ownership, keeps the memory and can restock the piece', function () {
    $staff = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $tag = LegacyTag::factory()->create(['product_variant_id' => $variant->id, 'status' => LegacyTagStatus::Delivered]);
    $story = app(ActivateTag::class)->handle($tag, User::factory()->create());

    app(ManageTag::class)->return($tag, $staff, 'Cliente devolvió en 7 días', $warehouse);

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::InStock)
        ->and($tag->fresh()->owner_athlete_id)->toBeNull()
        ->and($story->fresh())->not->toBeNull()
        ->and($story->fresh()->legacy_tag_id)->toBeNull()
        ->and(onHand($variant->id, $warehouse->id))->toBe(1);
});

test('disabling stock removes it from available inventory and enabling restores it', function () {
    $staff = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $tag = tagStock($variant, $warehouse, 3, $staff)->first();

    app(ManageTag::class)->disable($tag, $staff, 'Correa rota');
    expect(onHand($variant->id, $warehouse->id))->toBe(2)->and($tag->fresh()->status)->toBe(LegacyTagStatus::Disabled);

    app(ManageTag::class)->enable($tag, $staff);
    expect(onHand($variant->id, $warehouse->id))->toBe(3)->and($tag->fresh()->status)->toBe(LegacyTagStatus::InStock);
});
