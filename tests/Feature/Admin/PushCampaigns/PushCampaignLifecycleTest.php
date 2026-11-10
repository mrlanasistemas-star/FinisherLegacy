<?php

/**
 * Push campaigns — admin lifecycle (docs/renovacion/campanas-push.md):
 * permission gating, validation (no HTML, limits, finisherlegacy.com links,
 * no transactional categories), send now through PushSender (faked) +
 * notification-center copies, scheduling in America/Mexico_City and the
 * every-minute dispatcher, cancel / reschedule, the "Todos" rate guard,
 * test send, duplicate, image upload and app opens.
 */

use App\Models\NotificationPreference;
use App\Models\PushCampaign;
use App\Models\PushCampaignRecipient;
use App\Models\PushDevice;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\PushSender;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Admin\PushCampaigns\FakePushSender;

const PC_BASE = '/admin/notificaciones/campanas';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->push = new FakePushSender;
    $this->app->instance(PushSender::class, $this->push);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function pclReachable(bool $promotions = true): User
{
    $user = User::factory()->create();
    PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'ios',
        'provider' => 'expo', 'token' => 'ExponentPushToken['.Str::random(12).']', 'active' => true,
    ]);

    if ($promotions) {
        NotificationPreference::create([
            'user_id' => $user->id, 'scope' => NotificationPreference::SCOPE_CUSTOMER,
            'category' => 'promociones', 'push' => true, 'in_app' => true, 'email' => false,
        ]);
    }

    return $user;
}

/** @return array<string, mixed> */
function pclPayload(array $overrides = []): array
{
    return [
        'intent' => 'draft',
        'title' => 'Nueva colección Desierto',
        'body' => 'Ya puedes ver los nuevos diseños en la tienda.',
        'include_logo' => true,
        'color' => '#C9A45C',
        'channel' => 'avisos',
        'category' => 'promociones',
        'save_to_inbox' => true,
        'link_type' => 'store',
        'audience_type' => 'all',
        'audience_params' => [],
        ...$overrides,
    ];
}

test('the panel and every action require notifications.broadcast', function () {
    $staff = User::factory()->create();
    $role = Role::findOrCreate('pc_staff');
    $role->syncPermissions(['dashboard.admin.view', 'notifications.send']);
    $staff->assignRole($role);

    $campaign = PushCampaign::create(pclPayload(['created_by' => $this->admin->id]));

    $this->actingAs($staff)->get(PC_BASE)->assertForbidden();
    $this->actingAs($staff)->get(PC_BASE.'/crear')->assertForbidden();
    $this->actingAs($staff)->post(PC_BASE, pclPayload(['intent' => 'send']))->assertForbidden();
    $this->actingAs($staff)->post(PC_BASE."/{$campaign->uuid}/cancelar")->assertForbidden();
    $this->actingAs($staff)->getJson(PC_BASE.'/alcance?audience_type=all&category=promociones')->assertForbidden();

    expect(PushCampaign::query()->count())->toBe(1)->and($this->push->calls)->toBe([]);

    $this->actingAs($this->admin)->get(PC_BASE)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/campaigns/Index')->has('campaigns.data', 1));
    $this->actingAs($this->admin)->get(PC_BASE.'/crear')
        ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/campaigns/Compose'));
});

test('content is plain text within the limits, links stay on finisherlegacy.com and transactional categories are refused', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->post(PC_BASE, pclPayload($overrides))->assertSessionHasErrors($field);

    expect(PushCampaign::query()->count())->toBe(0);
})->with([
    'html in title' => [['title' => '<b>Hola</b>'], 'title'],
    'script in body' => [['body' => 'Hola <script>alert(1)</script>'], 'body'],
    'title too long' => [['title' => str_repeat('a', 66)], 'title'],
    'body too long' => [['body' => str_repeat('a', 241)], 'body'],
    'external link' => [['link_type' => 'url', 'link_value' => 'https://example.com/promo'], 'link_value'],
    'http link' => [['link_type' => 'url', 'link_value' => 'http://finisherlegacy.com/promo'], 'link_value'],
    'look-alike host' => [['link_type' => 'url', 'link_value' => 'https://finisherlegacy.com.evil.io/'], 'link_value'],
    'transactional category' => [['category' => 'pedidos'], 'category'],
    'avisos to everyone' => [['category' => 'avisos'], 'category'],
    'bad color' => [['color' => 'red'], 'color'],
    'unknown channel' => [['channel' => 'pedidos'], 'channel'],
]);

test('send now freezes the audience, saves inbox copies and pushes once through PushSender', function () {
    $a = pclReachable();
    $b = pclReachable();
    pclReachable(promotions: false); // default OFF for promotions push → not in "Todos"

    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['intent' => 'send', 'link_type' => 'url', 'link_value' => 'https://finisherlegacy.com/colecciones/desierto']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $campaign = PushCampaign::query()->sole();

    expect($campaign->status)->toBe(PushCampaign::STATUS_SENT)
        ->and($campaign->audience_users_count)->toBe(2)
        ->and($campaign->sent_count)->toBe(2)
        ->and($campaign->inbox_count)->toBe(2)
        ->and($campaign->sent_by)->toBe($this->admin->id)
        ->and($this->push->calls)->toHaveCount(1)
        ->and($this->push->pushedUserIds())->toEqualCanonicalizing([$a->id, $b->id]);

    $message = $this->push->calls[0]['message'];
    expect($message->title)->toBe('Nueva colección Desierto')
        ->and($message->deepLink)->toBe('https://finisherlegacy.com/colecciones/desierto')
        ->and($message->color)->toBe('#C9A45C')
        ->and($message->channel)->toBe('avisos')
        ->and($message->category?->value)->toBe('promociones')
        ->and($message->data['campaign_id'])->toBe($campaign->uuid)
        // No custom image + logo on → the official monogram.
        ->and($message->imageUrl)->toEndWith('images/brand/logo/fl-monograma.png');

    expect($a->notifications()->count())->toBe(1)
        ->and($a->notifications()->first()->data['metadata']['campaign_uuid'])->toBe($campaign->uuid)
        ->and(PushCampaignRecipient::query()->where('status', 'sent')->count())->toBe(2);

    // Nothing is sent twice by a later scheduler run.
    $this->artisan('push-campaigns:dispatch')->assertSuccessful();
    expect($this->push->calls)->toHaveCount(1);
});

test('scheduling stores Mexico City wall time as UTC and the every-minute command sends it when due', function () {
    $this->travelTo(now()->setTimezone('America/Mexico_City')->setDate(2026, 11, 20)->setTime(9, 0));
    $user = pclReachable();

    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['intent' => 'schedule', 'scheduled_at' => '2026-11-20T18:30']))
        ->assertSessionHasNoErrors();

    $campaign = PushCampaign::query()->sole();

    expect($campaign->status)->toBe(PushCampaign::STATUS_SCHEDULED)
        // Mexico City has no DST since 2022: UTC-6.
        ->and($campaign->scheduled_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-21 00:30');

    $this->artisan('push-campaigns:dispatch')->assertSuccessful();
    expect($campaign->fresh()->status)->toBe(PushCampaign::STATUS_SCHEDULED)->and($this->push->calls)->toBe([]);

    $this->travelTo($campaign->scheduled_at->copy()->addSeconds(30));
    $this->artisan('push-campaigns:dispatch')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(PushCampaign::STATUS_SENT)
        ->and($this->push->pushedUserIds())->toBe([$user->id]);
});

test('a past schedule time is refused', function () {
    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['intent' => 'schedule', 'scheduled_at' => now('America/Mexico_City')->subHour()->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('scheduled_at');
});

test('a scheduled campaign can be cancelled or rescheduled, never once it was sent', function () {
    pclReachable();
    $at = now('America/Mexico_City')->addDay()->format('Y-m-d\TH:i');

    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'schedule', 'scheduled_at' => $at]));
    $campaign = PushCampaign::query()->sole();

    $later = now('America/Mexico_City')->addDays(2)->format('Y-m-d\TH:i');
    $this->actingAs($this->admin)
        ->post(PC_BASE."/{$campaign->uuid}", pclPayload(['intent' => 'schedule', 'scheduled_at' => $later]))
        ->assertSessionHasNoErrors();
    expect($campaign->fresh()->scheduled_at->setTimezone('America/Mexico_City')->format('Y-m-d\TH:i'))->toBe($later);

    $this->actingAs($this->admin)->post(PC_BASE."/{$campaign->uuid}/cancelar")->assertRedirect();
    expect($campaign->fresh()->status)->toBe(PushCampaign::STATUS_CANCELLED)
        ->and($campaign->fresh()->cancelled_by)->toBe($this->admin->id);

    $this->travel(3)->days();
    $this->artisan('push-campaigns:dispatch')->assertSuccessful();
    expect($this->push->calls)->toBe([]);

    // Sent campaigns are history: no cancel, no edit.
    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'send', 'audience_type' => 'users', 'category' => 'avisos', 'audience_params' => ['user_ids' => [$this->admin->id]]]));
    $sent = PushCampaign::query()->where('status', PushCampaign::STATUS_SENT)->sole();

    $this->actingAs($this->admin)->post(PC_BASE."/{$sent->uuid}/cancelar")->assertSessionHasErrors('campaign');
    $this->actingAs($this->admin)->post(PC_BASE."/{$sent->uuid}", pclPayload())->assertSessionHasErrors('campaign');
    $this->actingAs($this->admin)->get(PC_BASE."/{$sent->uuid}/editar")->assertRedirect(PC_BASE."/{$sent->uuid}");

    expect(pclActivity($campaign))->toContain('push_campaign.created', 'push_campaign.scheduled', 'push_campaign.rescheduled', 'push_campaign.cancelled');
});

test('at most one broadcast to everyone per hour (configurable); targeted audiences are not limited', function () {
    pclReachable();

    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'send']))->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'send', 'title' => 'Otra']))->assertSessionHasErrors('audience_type');
    $second = PushCampaign::query()->where('title', 'Otra')->sole();
    expect($second->status)->toBe(PushCampaign::STATUS_DRAFT);

    // Scheduling inside the hour is refused too; two hours later is fine.
    $this->actingAs($this->admin)
        ->post(PC_BASE."/{$second->uuid}", pclPayload(['intent' => 'schedule', 'title' => 'Otra', 'scheduled_at' => now('America/Mexico_City')->addMinutes(30)->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('audience_type');
    $this->actingAs($this->admin)
        ->post(PC_BASE."/{$second->uuid}", pclPayload(['intent' => 'schedule', 'title' => 'Otra', 'scheduled_at' => now('America/Mexico_City')->addHours(2)->format('Y-m-d\TH:i')]))
        ->assertSessionHasNoErrors();

    // A targeted campaign right away is fine.
    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['intent' => 'send', 'title' => 'Dirigida', 'audience_type' => 'opted_in']))
        ->assertSessionHasNoErrors();

    config(['push_campaigns.all_audience_per_hour' => 3]);
    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'send', 'title' => 'Tercera']))->assertSessionHasNoErrors();
    expect(PushCampaign::query()->where('title', 'Tercera')->sole()->status)->toBe(PushCampaign::STATUS_SENT);
});

test('a scheduled broadcast that would break the limit when due is blocked, not sent', function () {
    pclReachable();
    $campaign = PushCampaign::create(pclPayload(['status' => PushCampaign::STATUS_SCHEDULED, 'scheduled_at' => now()->subMinute()]));
    PushCampaign::create(pclPayload(['status' => PushCampaign::STATUS_SENT, 'started_at' => now()->subMinutes(10)]));

    $this->artisan('push-campaigns:dispatch')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(PushCampaign::STATUS_FAILED)
        ->and($campaign->fresh()->last_error)->toContain('última hora')
        ->and($this->push->calls)->toBe([]);
});

test('test send goes only to the admin, unfiltered by preferences, and is labelled', function () {
    $other = pclReachable();

    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'test']))->assertSessionHasNoErrors();

    $campaign = PushCampaign::query()->sole();

    expect($campaign->status)->toBe(PushCampaign::STATUS_DRAFT)
        ->and($this->push->calls)->toHaveCount(1)
        ->and($this->push->calls[0]['users'])->toBe([$this->admin->id])
        ->and($this->push->calls[0]['message']->title)->toStartWith('[Prueba]')
        ->and($this->push->calls[0]['message']->category)->toBeNull()
        ->and($other->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(1);
});

test('image upload: jpg/png up to 1 MB on the public disk; duplicate copies it into a new draft', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['image' => UploadedFile::fake()->image('big.png')->size(1500)]))
        ->assertSessionHasErrors('image');
    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['image' => UploadedFile::fake()->create('a.gif', 10, 'image/gif')]))
        ->assertSessionHasErrors('image');

    $this->actingAs($this->admin)
        ->post(PC_BASE, pclPayload(['image' => UploadedFile::fake()->image('ok.jpg')->size(300)]))
        ->assertSessionHasNoErrors();

    $campaign = PushCampaign::query()->sole();
    Storage::disk('public')->assertExists($campaign->image_path);

    $this->actingAs($this->admin)->post(PC_BASE."/{$campaign->uuid}/duplicar")->assertRedirect();

    $copy = PushCampaign::query()->whereKeyNot($campaign->id)->sole();
    expect($copy->status)->toBe(PushCampaign::STATUS_DRAFT)
        ->and($copy->duplicated_from_id)->toBe($campaign->id)
        ->and($copy->title)->toBe($campaign->title)
        ->and($copy->image_path)->not->toBe($campaign->image_path);
    Storage::disk('public')->assertExists($copy->image_path);
});

test('the app reports opens once per recipient by campaign id or inbox id', function () {
    $user = pclReachable();
    $stranger = User::factory()->create();

    $this->actingAs($this->admin)->post(PC_BASE, pclPayload(['intent' => 'send']));
    $campaign = PushCampaign::query()->sole();

    $this->postJson("/api/v1/notifications/{$campaign->uuid}/opened", [], apiAuthHeader($stranger))
        ->assertOk()->assertJsonPath('data.recorded', false);

    $this->postJson("/api/v1/notifications/{$campaign->uuid}/opened", [], apiAuthHeader($user))
        ->assertOk()->assertJsonPath('data.recorded', true);

    $inboxId = $user->notifications()->first()->id;
    $this->postJson("/api/v1/notifications/{$inboxId}/opened", [], apiAuthHeader($user))
        ->assertOk()->assertJsonPath('data.recorded', false);

    expect(PushCampaignRecipient::query()->whereNotNull('opened_at')->count())->toBe(1);

    $this->actingAs($this->admin)->get(PC_BASE)
        ->assertInertia(fn (Assert $page) => $page->where('campaigns.data.0.opened_count', 1));
});

test('the panel shows delivered and failed from the push receipts next to the queued count', function () {
    $campaign = PushCampaign::create(pclPayload([
        'status' => PushCampaign::STATUS_SENT, 'started_at' => now()->subHour(), 'completed_at' => now()->subHour(),
        'sent_count' => 10, 'delivered_count' => 6, 'delivery_failed_count' => 1,
    ]));

    $this->actingAs($this->admin)->get("/admin/notificaciones/campanas/{$campaign->uuid}")
        ->assertInertia(fn ($page) => $page
            ->component('admin/notifications/campaigns/Show')
            ->where('campaign.sent_count', 10)
            ->where('campaign.delivered_count', 6)
            ->where('campaign.delivery_failed_count', 1)
            ->where('campaign.awaiting_receipt_count', 3));

    $this->actingAs($this->admin)->get('/admin/notificaciones/campanas')
        ->assertInertia(fn ($page) => $page
            ->where('campaigns.data.0.delivered_count', 6)
            ->where('campaigns.data.0.delivery_failed_count', 1));
});

test('the live estimate answers for the composer', function () {
    pclReachable();
    pclReachable(promotions: false);

    $this->actingAs($this->admin)
        ->getJson(PC_BASE.'/alcance?'.http_build_query(['audience_type' => 'all', 'category' => 'promociones', 'save_to_inbox' => 1]))
        ->assertOk()
        ->assertJson(['users' => 1, 'devices' => 1, 'users_with_push' => 1, 'rate_limited_now' => false]);
});

/** @return list<string> */
function pclActivity(PushCampaign $campaign): array
{
    return Activity::query()
        ->where('subject_type', $campaign->getMorphClass())
        ->where('subject_id', $campaign->id)
        ->pluck('description')
        ->all();
}
