<?php

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\SupportCaseType;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Models\Ally;
use App\Models\LegacyTag;
use App\Models\LegacyTagScan;
use App\Models\Order;
use App\Models\SupportCase;
use App\Queries\Admin\CommerceDashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('with an empty database every rate and median is null — never a fake number', function () {
    $m = app(CommerceDashboardMetrics::class)->handle('30d');

    expect($m['sales']['total_minor'])->toBeNull()
        ->and($m['sales']['orders'])->toBe(0)
        ->and($m['sales']['average_ticket_minor'])->toBeNull()
        ->and($m['sales']['units'])->toBe(0)
        ->and(collect($m['channels'])->pluck('share')->filter()->all())->toBe([])
        ->and($m['inventory']['units_available'])->toBe(0)
        ->and($m['activation']['rate'])->toBeNull()
        ->and($m['activation']['within_window_rate'])->toBeNull()
        ->and($m['activation']['median_seconds'])->toBeNull()
        ->and($m['collections']['top'])->toBeNull()
        ->and($m['pos']['median_seconds'])->toBeNull()
        ->and($m['pos']['within_target_rate'])->toBeNull()
        ->and($m['product']['opens_total'])->toBe(0);
});

test('the admin dashboard renders the commerce block for the chosen range', function () {
    $this->actingAs(tagStaff())->get('/admin?rango=hoy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('range', 'hoy')
            ->where('commerce.range', 'hoy')
            ->where('commerce.sales.total_minor', null)
            ->has('commerce.channels', 4)
            ->has('today.events'));
});

test('seeded sales split by channel, units exclude nothing physical, activation and 72h are measured honestly', function () {
    $staff = tagStaff();
    $variant = tagVariant(40000);
    $ally = Ally::factory()->create();
    $allyLocation = tagLocation(InventoryLocationType::Ally, $ally);
    $warehouse = tagLocation();

    $allyTags = tagStock($variant, $allyLocation, 3, $staff);
    $directTags = tagStock($variant, $warehouse, 3, $staff);

    $allySale = inPersonSale($allyLocation, [$allyTags[0]->public_code, $allyTags[1]->public_code], $staff);
    inPersonSale($warehouse, [$directTags[0]->public_code], $staff);

    // POS duration fixtures: 60 s and 120 s.
    $allySale->forceFill(['sale_started_at' => $allySale->created_at->subSeconds(60)])->save();
    Order::query()->where('sales_channel', 'direct')->first()->forceFill(['sale_started_at' => now()->subSeconds(120)])->save();

    // Activation: one ally tag sold 5 days ago and activated 10 h later (within 72 h),
    // the direct tag sold 5 days ago and activated 100 h later (outside 72 h).
    $soldAt = CarbonImmutable::now()->subDays(5);
    LegacyTag::query()->whereKey($allyTags[0]->id)->update(['status' => LegacyTagStatus::Activated->value, 'sold_at' => $soldAt, 'activated_at' => $soldAt->addHours(10)]);
    LegacyTag::query()->whereKey($directTags[0]->id)->update(['status' => LegacyTagStatus::Activated->value, 'sold_at' => $soldAt, 'activated_at' => $soldAt->addHours(100)]);

    LegacyTagScan::query()->create(['legacy_tag_id' => $allyTags[0]->id, 'event' => TagScanEvent::TagOpened->value, 'source' => TagAccessSource::Nfc->value, 'created_at' => now()]);
    LegacyTagScan::query()->create(['legacy_tag_id' => $allyTags[0]->id, 'event' => TagScanEvent::TagOpened->value, 'source' => TagAccessSource::Qr->value, 'created_at' => now()]);
    SupportCase::query()->create(['uuid' => (string) Str::uuid(), 'number' => 'SC-TEST-0001', 'type' => SupportCaseType::Defect, 'subject' => 'NFC no lee', 'legacy_tag_id' => $allyTags[1]->id]);

    $m = app(CommerceDashboardMetrics::class)->handle('30d');
    $channels = collect($m['channels'])->keyBy('channel');

    expect($m['sales']['orders'])->toBe(2)
        ->and($m['sales']['total_minor'])->toBe(120000)
        ->and($m['sales']['average_ticket_minor'])->toBe(60000)
        ->and($m['sales']['units'])->toBe(3)
        ->and($channels['ally']['orders'])->toBe(1)
        ->and($channels['ally']['units'])->toBe(2)
        ->and($channels['direct']['units'])->toBe(1)
        ->and($channels['online']['orders'])->toBe(0)
        ->and($channels['ally']['share'])->toBe(round(80000 / 120000, 4))
        ->and($m['inventory']['units_available'])->toBe(3)
        ->and($m['activation']['sold'])->toBe(3)
        ->and($m['activation']['activated'])->toBe(2)
        ->and($m['activation']['pending'])->toBe(1)
        ->and($m['activation']['rate'])->toBe(round(2 / 3, 4))
        // Only the two tags sold ≥72 h ago are judged; one of them made it.
        ->and($m['activation']['mature_sold'])->toBe(2)
        ->and($m['activation']['within_window_rate'])->toBe(0.5)
        ->and($m['activation']['median_seconds'])->toBe(55 * 3600)
        ->and($m['product']['opens']['nfc'])->toBe(1)
        // An old `?src=qr` link counts as a link: the tag has no printed QR any more.
        ->and($m['product']['opens'])->not->toHaveKey('qr')
        ->and($m['product']['opens']['link'])->toBe(1)
        ->and($m['product']['tags_with_incidents'])->toBe(1)
        ->and($m['product']['defects'])->toBe(1)
        ->and($m['collections']['top']['units'])->toBe(3)
        ->and($m['collections']['top']['available'])->toBe(3)
        ->and($m['pos']['sales'])->toBe(2)
        ->and($m['pos']['median_seconds'])->toBe(90)
        ->and($m['pos']['within_target_rate'])->toBe(0.5);
});

test('medians are computed over sorted values', function () {
    expect(CommerceDashboardMetrics::median([]))->toBeNull()
        ->and(CommerceDashboardMetrics::median([30, 10, 20]))->toBe(20)
        ->and(CommerceDashboardMetrics::median([40, 10, 20, 30]))->toBe(25);
});
