<?php

/**
 * Entrenamientos — moderation (docs/renovacion/entrenamientos.md
 * §Moderación): the athlete's «Reportar mensaje» (API + web), the
 * privacy-respecting admin page (aggregates only; content just for
 * reported messages), disabling links, purging a reported sender, the
 * audited short signed audio URL and rate-limit hit recording.
 */

use App\Enums\CheerMessageStatus;
use App\Enums\MediaFileStatus;
use App\Enums\ReportStatus;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerMessageReport;
use App\Models\CheerRateLimitHit;
use App\Models\MediaFile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/training-helpers.php';

beforeEach(function () {
    Cache::flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
    Notification::fake();
    $this->withoutVite();
    config(['training.cheers.submit_per_ip_per_minute' => 100, 'training.cheers.submit_per_ip_per_hour' => 100]);
});

function moderationCheer(CheerLink $link, array $overrides = [], string $ip = '10.0.0.1'): CheerMessage
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/animo/'.$link->token, cheerPayload($overrides))
        ->assertSessionHasNoErrors();

    return CheerMessage::query()->latest('id')->firstOrFail();
}

function cheerModerator(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('the athlete reports a message through the API: 204, hidden, reviewable', function () {
    $link = cheerLinkFor();
    $message = moderationCheer($link, ['body' => 'Mensaje ofensivo']);

    $this->postJson("/api/v1/training/messages/{$message->uuid}/report", ['reason' => 'harassment', 'details' => 'Me insulta'], apiAuthHeader($link->user))
        ->assertNoContent();

    $report = CheerMessageReport::query()->sole();
    expect($report->reason->value)->toBe('harassment')
        ->and($report->details)->toBe('Me insulta')
        ->and($report->status)->toBe(ReportStatus::Open)
        ->and($report->sender_ip_hash)->toBe($message->sender_ip_hash)
        ->and($message->refresh()->status)->toBe(CheerMessageStatus::Hidden);

    expect(Activity::query()->where('event', 'cheer_message.reported')->exists())->toBeTrue();
});

test('the report endpoint validates the reason and is owner-only', function () {
    $link = cheerLinkFor();
    $message = moderationCheer($link);

    $this->postJson("/api/v1/training/messages/{$message->uuid}/report", ['reason' => 'nope'], apiAuthHeader($link->user))
        ->assertUnprocessable();

    $this->postJson("/api/v1/training/messages/{$message->uuid}/report", ['reason' => 'spam'], apiAuthHeader(User::factory()->create()))
        ->assertNotFound();

    expect(CheerMessageReport::query()->count())->toBe(0);
});

test('the athlete can report from /mi-entrenamiento', function () {
    $link = cheerLinkFor();
    $message = moderationCheer($link);

    $this->actingAs($link->user)
        ->post("/mi-entrenamiento/mensajes/{$message->uuid}/reportar", ['reason' => 'inappropriate'])
        ->assertRedirect();

    expect(CheerMessageReport::query()->sole()->reason->value)->toBe('inappropriate');
});

test('the admin page is gated and shows only aggregates for unreported messages', function () {
    $link = cheerLinkFor();
    moderationCheer($link, ['body' => 'Texto privado que nadie del equipo debe ver']);
    $reported = moderationCheer($link, ['body' => 'Texto reportado'], '10.0.0.2');
    $this->postJson("/api/v1/training/messages/{$reported->uuid}/report", ['reason' => 'spam'], apiAuthHeader($link->user))->assertNoContent();

    $this->actingAs(User::factory()->create())->get('/admin/entrenamientos')->assertForbidden();

    $response = $this->actingAs(cheerModerator())->get('/admin/entrenamientos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/training/Index')
            ->where('stats.messages', 2)
            ->where('stats.open_reports', 1)
            ->has('reports', 1)
            ->where('reports.0.message.body', 'Texto reportado')
            ->where('links.0.uuid', $link->uuid)
            ->where('links.0.messages_count', 2)
            ->where('links.0.reports_count', 1)
            ->where('athletes.0.messages', 2));

    expect($response->getContent())->not->toContain('Texto privado que nadie del equipo debe ver')
        ->and($response->getContent())->not->toContain($reported->sender_ip_hash);
});

test('a moderator disables and re-enables a link; a disabled link stops accepting', function () {
    $link = cheerLinkFor();
    $admin = cheerModerator();

    $this->actingAs($admin)->post("/admin/entrenamientos/enlaces/{$link->uuid}/desactivar")->assertRedirect();
    expect($link->refresh()->moderation_disabled_at)->not->toBeNull()
        ->and($link->isAccepting())->toBeFalse();

    $this->post('/animo/'.$link->token, cheerPayload());
    expect(CheerMessage::query()->count())->toBe(0);

    $this->actingAs($admin)->post("/admin/entrenamientos/enlaces/{$link->uuid}/reactivar")->assertRedirect();
    expect($link->refresh()->isAccepting())->toBeTrue()
        ->and(Activity::query()->where('event', 'cheer_link.disabled')->exists())->toBeTrue();
});

test('purging a reported sender deletes their messages for that athlete and blocks them', function () {
    $link = cheerLinkFor();
    $other = cheerLinkFor($link->user);
    $bad = moderationCheer($link, ['body' => 'uno'], '10.0.0.9');
    moderationCheer($other, ['body' => 'dos'], '10.0.0.9');
    $kept = moderationCheer($link, ['body' => 'otra persona'], '10.0.0.10');

    $this->postJson("/api/v1/training/messages/{$bad->uuid}/report", ['reason' => 'threat'], apiAuthHeader($link->user))->assertNoContent();
    $report = CheerMessageReport::query()->sole();

    $this->actingAs(cheerModerator())->post("/admin/entrenamientos/reportes/{$report->uuid}/purgar")->assertRedirect();

    expect(CheerMessage::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(CheerBlock::query()->where('cheer_link_id', $link->id)->where('sender_ip_hash', $bad->sender_ip_hash)->exists())->toBeTrue()
        ->and($report->refresh()->status)->toBe(ReportStatus::Resolved)
        ->and($report->cheer_message_id)->toBeNull();
});

test('dismissing a report can put the message back in the athlete list', function () {
    $link = cheerLinkFor();
    $message = moderationCheer($link);
    $this->postJson("/api/v1/training/messages/{$message->uuid}/report", ['reason' => 'other'], apiAuthHeader($link->user))->assertNoContent();
    $report = CheerMessageReport::query()->sole();

    $this->actingAs(cheerModerator())
        ->patch("/admin/entrenamientos/reportes/{$report->uuid}", ['status' => 'dismissed', 'restore_message' => true])
        ->assertRedirect();

    expect($report->refresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($message->refresh()->status)->toBe(CheerMessageStatus::Visible);
});

test('audio of a reported message is a short signed url and every access is audited', function () {
    $link = cheerLinkFor();
    $message = moderationCheer($link, ['body' => null, 'audio' => cheerUpload('voz.mp3', mp3Bytes()), 'duration_seconds' => 20]);
    MediaFile::query()->whereKey($message->audio_media_file_id)->update([
        'status' => MediaFileStatus::Ready->value,
        'path' => 'users/'.$link->user_id.'/support/x.mp3',
    ]);
    $this->postJson("/api/v1/training/messages/{$message->uuid}/report", ['reason' => 'harassment'], apiAuthHeader($link->user))->assertNoContent();
    $report = CheerMessageReport::query()->sole();
    $admin = cheerModerator();

    $this->actingAs($admin)->post("/admin/entrenamientos/reportes/{$report->uuid}/audio")
        ->assertRedirect()
        ->assertInertiaFlash('cheer_report_audio.report', $report->uuid);

    $log = Activity::query()->where('event', 'cheer_report.audio_accessed')->sole();
    expect($log->causer_id)->toBe($admin->id);

    // Unreported audio has no admin route at all; a non-moderator gets 403.
    $this->actingAs(User::factory()->create())->post("/admin/entrenamientos/reportes/{$report->uuid}/audio")->assertForbidden();
});

test('throttled public submits are recorded as abuse signals (ip hash only)', function () {
    config(['training.cheers.submit_per_ip_per_minute' => 1]);
    $link = cheerLinkFor();

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->post('/animo/'.$link->token, cheerPayload())->assertSessionHasNoErrors();
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->post('/animo/'.$link->token, cheerPayload())->assertSessionHasErrors('form');

    $hit = CheerRateLimitHit::query()->sole();
    expect($hit->cheer_link_id)->toBe($link->id)
        ->and($hit->scope)->toBe('ip_minute')
        ->and($hit->sender_ip_hash)->not->toBe('10.1.1.1')
        ->and(strlen((string) $hit->sender_ip_hash))->toBe(64);
});
