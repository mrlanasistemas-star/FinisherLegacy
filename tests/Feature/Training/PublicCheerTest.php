<?php

/**
 * Entrenamientos — the public `/animo/{token}` page and submit: audio and
 * text messages, validation (real MIME, size, duration), rate limits,
 * revoked/expired/full links and blocked senders.
 */

use App\Actions\Training\ManageCheerMessages;
use App\Enums\CheerMessageStatus;
use App\Models\CheerMessage;
use App\Models\MediaFile;
use App\Services\Media\VideoTranscoder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/training-helpers.php';

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
    Notification::fake();
    $this->withoutVite();
    config(['training.cheers.submit_per_ip_per_minute' => 100, 'training.cheers.submit_per_ip_per_hour' => 100]);
});

test('the public page renders without an account and is never indexed', function () {
    $link = cheerLinkFor();

    $this->get('/animo/'.$link->token)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('training/Cheer')
            ->where('state', 'active')
            ->where('title', 'Mi maratón')
            ->where('athlete.name', $link->user->name)
            ->has('icons', 8)
            ->where('limits.max_audio_seconds', 180)
            ->missing('athlete.email'));
});

test('unknown tokens are a 404', function () {
    $this->get('/animo/'.str_repeat('a', 32))->assertNotFound();
});

test('a text-only message is stored for the link owner', function () {
    $link = cheerLinkFor();

    $this->from('/animo/'.$link->token)
        ->post('/animo/'.$link->token, cheerPayload())
        ->assertRedirect('/animo/'.$link->token)
        ->assertSessionHasNoErrors();

    $message = CheerMessage::query()->sole();
    expect($message->recipient_user_id)->toBe($link->user_id)
        ->and($message->body)->toBe('¡Tú puedes, hijo!')
        ->and($message->audio_media_file_id)->toBeNull()
        ->and($message->sender_ip_hash)->toHaveLength(64)
        ->and($message->sender_ip_hash)->not->toBe('127.0.0.1')
        ->and($link->fresh()->messages_count)->toBe(1);
});

test('a recorded webm voice message goes through the private media pipeline', function () {
    $link = cheerLinkFor();

    $this->post('/animo/'.$link->token, cheerPayload([
        'body' => null,
        'audio' => cheerUpload('animo.webm', webmBytes()),
        'duration_seconds' => 42,
    ]))->assertSessionHasNoErrors();

    $message = CheerMessage::query()->sole();
    $file = MediaFile::query()->sole();

    expect($message->audio_media_file_id)->toBe($file->id)
        ->and($message->duration_seconds)->toBe(42)
        ->and($file->mediable_type)->toBe($message->getMorphClass())
        ->and($file->mediable_id)->toBe($message->id)
        ->and($file->visibility->value)->toBe('private')
        ->and($file->owner_user_id)->toBe($link->user_id);
});

test('an mp3 upload is accepted too', function () {
    $link = cheerLinkFor();

    $this->post('/animo/'.$link->token, cheerPayload(['audio' => cheerUpload('voz.mp3', mp3Bytes())]))
        ->assertSessionHasNoErrors();

    expect(MediaFile::query()->sole()->mime)->toBe('audio/mpeg');
});

test('validation: name, icon, content, real audio bytes and size', function () {
    $link = cheerLinkFor();
    $url = '/animo/'.$link->token;

    $this->post($url, cheerPayload(['sender_name' => '']))->assertSessionHasErrors('sender_name');
    $this->post($url, cheerPayload(['icon' => 'skull']))->assertSessionHasErrors('icon');
    $this->post($url, cheerPayload(['body' => null]))->assertSessionHasErrors(['body', 'audio']);
    $this->post($url, cheerPayload(['body' => str_repeat('a', 501)]))->assertSessionHasErrors('body');
    $this->post($url, cheerPayload(['sender_name' => str_repeat('a', 61)]))->assertSessionHasErrors('sender_name');

    // A PNG renamed to .webm: the sniffed type wins.
    $png = cheerUpload('trampa.webm', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64));
    $this->post($url, cheerPayload(['audio' => $png]))->assertSessionHasErrors('audio');

    // Over 10 MB.
    $big = cheerUpload('largo.mp3', mp3Bytes().str_repeat("\0", 10 * 1024 * 1024 + 10));
    $this->post($url, cheerPayload(['audio' => $big]))->assertSessionHasErrors('audio');

    expect(CheerMessage::query()->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('audio longer than three minutes is rejected when ffprobe can measure it', function () {
    $this->mock(VideoTranscoder::class, fn ($mock) => $mock->shouldReceive('probe')->andReturn(['duration_ms' => 200_000, 'width' => null, 'height' => null]));
    $link = cheerLinkFor();

    $this->post('/animo/'.$link->token, cheerPayload(['audio' => cheerUpload('largo.mp3', mp3Bytes())]))
        ->assertSessionHasErrors('audio');

    $this->post('/animo/'.$link->token, cheerPayload(['audio' => cheerUpload('largo.mp3', mp3Bytes()), 'duration_seconds' => 400]))
        ->assertSessionHasErrors('duration_seconds');

    expect(CheerMessage::query()->count())->toBe(0)->and(MediaFile::query()->count())->toBe(0);
});

test('the probed duration wins over the browser value', function () {
    $this->mock(VideoTranscoder::class, fn ($mock) => $mock->shouldReceive('probe')->andReturn(['duration_ms' => 61_400, 'width' => null, 'height' => null]));
    $link = cheerLinkFor();

    $this->post('/animo/'.$link->token, cheerPayload(['audio' => cheerUpload('voz.mp3', mp3Bytes()), 'duration_seconds' => 5]))
        ->assertSessionHasNoErrors();

    expect(CheerMessage::query()->sole()->duration_seconds)->toBe(61);
});

test('revoked, expired and full links show a friendly state and refuse messages', function () {
    $revoked = cheerLinkFor();
    $revoked->forceFill(['revoked_at' => now()])->save();

    $expired = cheerLinkFor(days: 7);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $full = cheerLinkFor();
    $full->forceFill(['max_messages' => 1, 'messages_count' => 1])->save();

    foreach (['revoked' => $revoked, 'expired' => $expired, 'full' => $full] as $state => $link) {
        $this->get('/animo/'.$link->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('training/Cheer')->where('state', $state));

        $this->post('/animo/'.$link->token, cheerPayload())->assertSessionHasErrors('form');
    }

    expect(CheerMessage::query()->count())->toBe(0);
});

test('a blocked sender sees success but nothing is stored', function () {
    $link = cheerLinkFor();
    $this->post('/animo/'.$link->token, cheerPayload())->assertSessionHasNoErrors();

    $first = CheerMessage::query()->sole();
    app(ManageCheerMessages::class)->blockSender($first, $link->user);

    expect($first->fresh()->status)->toBe(CheerMessageStatus::Hidden);

    $this->post('/animo/'.$link->token, cheerPayload(['body' => 'otra vez']))->assertSessionHasNoErrors();

    expect(CheerMessage::query()->count())->toBe(1)
        ->and($link->fresh()->messages_count)->toBe(1);

    // Another sender (different IP) still gets through.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->post('/animo/'.$link->token, cheerPayload(['sender_name' => 'Tía']))
        ->assertSessionHasNoErrors();

    expect(CheerMessage::query()->count())->toBe(2);
});

test('submits are rate limited per IP with a friendly error', function () {
    config(['training.cheers.submit_per_ip_per_minute' => 4]);
    $link = cheerLinkFor();

    for ($i = 0; $i < 4; $i++) {
        $this->post('/animo/'.$link->token, cheerPayload())->assertSessionHasNoErrors();
    }

    $this->post('/animo/'.$link->token, cheerPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('form');

    expect(CheerMessage::query()->count())->toBe(4);
});

test('submits are rate limited per link across IPs', function () {
    config(['training.cheers.submit_per_link_per_hour' => 2]);
    $link = cheerLinkFor();

    foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/animo/'.$link->token, cheerPayload())->assertSessionHasNoErrors();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->post('/animo/'.$link->token, cheerPayload())
        ->assertSessionHasErrors('form');

    expect(CheerMessage::query()->count())->toBe(2);
});
