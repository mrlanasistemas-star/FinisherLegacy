<?php

/**
 * Entrenamientos — the mobile contract `/api/v1/training/*` and the
 * athlete web page: owner-only access (404 for others), signed audio URLs,
 * listened / favorite / hide / block, links, and the new-message
 * notification with its `training` deep-link target.
 */

use App\Enums\CheerMessageStatus;
use App\Enums\MediaFileStatus;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\MediaFile;
use App\Models\User;
use App\Notifications\AthleteAlert;
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

/** Leaves one cheer through the public page and returns it. */
function sendCheer(CheerLink $link, array $overrides = [], string $ip = '127.0.0.1'): CheerMessage
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/animo/'.$link->token, cheerPayload($overrides))
        ->assertSessionHasNoErrors();

    return CheerMessage::query()->latest('id')->firstOrFail();
}

test('the athlete is notified of each new message with the training target', function () {
    $link = cheerLinkFor();
    $message = sendCheer($link);

    Notification::assertSentTo($link->user, AthleteAlert::class, function (AthleteAlert $alert) use ($message) {
        return $alert->type->value === 'training_cheer'
            && $alert->metadata['target'] === ['type' => 'training', 'uuid' => $message->uuid]
            && $alert->metadata['deep_link'] === 'finisherlegacy://training';
    });
});

test('messages list follows the contract with a signed audio url', function () {
    $link = cheerLinkFor();
    $owner = $link->user;
    $message = sendCheer($link, ['body' => null, 'audio' => cheerUpload('voz.mp3', mp3Bytes()), 'duration_seconds' => 30]);

    // Simulate the queue having finished the pipeline.
    MediaFile::query()->whereKey($message->audio_media_file_id)->update([
        'status' => MediaFileStatus::Ready->value,
        'path' => 'users/'.$owner->id.'/support/x.mp3',
    ]);

    $response = $this->getJson('/api/v1/training/messages', apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.unheard_count', 1)
        ->assertJsonPath('data.0.uuid', $message->uuid)
        ->assertJsonPath('data.0.sender_name', 'Mamá')
        ->assertJsonPath('data.0.icon', 'heart')
        ->assertJsonPath('data.0.duration_seconds', 30)
        ->assertJsonPath('data.0.favorite', false)
        ->assertJsonPath('data.0.listened_at', null)
        ->assertJsonPath('data.0.link.uuid', $link->uuid)
        ->assertJsonPath('data.0.link.title', 'Mi maratón');

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'uuid', 'sender_name', 'relationship', 'body', 'icon', 'audio_url', 'duration_seconds', 'listened_at', 'favorite', 'created_at', 'link',
    ]);

    $url = (string) $response->json('data.0.audio_url');
    expect($url)->toContain('signature=')->toContain('expires=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect((int) $query['expires'])->toBeGreaterThanOrEqual(now()->addMinutes(60)->getTimestamp());

    // Without the signature, a stranger cannot fetch the private file.
    $this->app['auth']->forgetGuards();
    $this->get(strtok($url, '?'))->assertForbidden();
});

test('filters: unheard and favorites', function () {
    $link = cheerLinkFor();
    $owner = $link->user;
    $a = sendCheer($link, ['sender_name' => 'A'], '10.0.0.1');
    sendCheer($link, ['sender_name' => 'B'], '10.0.0.2');

    $headers = apiAuthHeader($owner);
    $this->postJson("/api/v1/training/messages/{$a->uuid}/listened", [], $headers)->assertNoContent();
    $this->postJson("/api/v1/training/messages/{$a->uuid}/favorite", ['favorite' => true], $headers)->assertNoContent();

    $this->getJson('/api/v1/training/messages?filter=unheard', $headers)
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.sender_name', 'B')->assertJsonPath('meta.unheard_count', 1);
    $this->getJson('/api/v1/training/messages?filter=favorites', $headers)
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.sender_name', 'A')->assertJsonPath('data.0.favorite', true);
    $this->getJson('/api/v1/training/messages?filter=nope', $headers)->assertUnprocessable();

    expect($a->fresh()->listened_at)->not->toBeNull()->and($a->fresh()->favorite)->toBeTrue();
});

test('hide removes a message from the playlist and block-sender blocks its IP hash', function () {
    $link = cheerLinkFor();
    $owner = $link->user;
    $a = sendCheer($link, ['sender_name' => 'A'], '10.0.0.1');
    $a2 = sendCheer($link, ['sender_name' => 'A otra vez'], '10.0.0.1');
    $b = sendCheer($link, ['sender_name' => 'B'], '10.0.0.2');
    $headers = apiAuthHeader($owner);

    $this->deleteJson("/api/v1/training/messages/{$b->uuid}", [], $headers)->assertNoContent();
    expect($b->fresh()->status)->toBe(CheerMessageStatus::Hidden);

    $this->postJson("/api/v1/training/messages/{$a->uuid}/block-sender", [], $headers)->assertNoContent();
    expect($a->fresh()->status)->toBe(CheerMessageStatus::Hidden)
        ->and($a2->fresh()->status)->toBe(CheerMessageStatus::Hidden)
        ->and(CheerBlock::query()->where('cheer_link_id', $link->id)->count())->toBe(1);

    $this->getJson('/api/v1/training/messages', $headers)->assertJsonCount(0, 'data');

    // A hidden message is gone for the API too.
    $this->postJson("/api/v1/training/messages/{$b->uuid}/listened", [], $headers)->assertNotFound();
});

test('every message and link endpoint is owner-only (404 for others)', function () {
    $link = cheerLinkFor();
    $message = sendCheer($link);
    $stranger = User::factory()->create();
    $headers = apiAuthHeader($stranger);

    $this->getJson('/api/v1/training/messages', $headers)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/training/links', $headers)->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/training/messages/{$message->uuid}/listened", [], $headers)->assertNotFound();
    $this->postJson("/api/v1/training/messages/{$message->uuid}/favorite", ['favorite' => true], $headers)->assertNotFound();
    $this->postJson("/api/v1/training/messages/{$message->uuid}/block-sender", [], $headers)->assertNotFound();
    $this->deleteJson("/api/v1/training/messages/{$message->uuid}", [], $headers)->assertNotFound();
    $this->deleteJson("/api/v1/training/links/{$link->uuid}", [], $headers)->assertNotFound();

    expect($message->fresh()->status)->toBe(CheerMessageStatus::Visible)
        ->and($message->fresh()->listened_at)->toBeNull()
        ->and($link->fresh()->revoked_at)->toBeNull();

    // Guests get 401.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/training/messages')->assertUnauthorized();
});

test('links: create, list and revoke through the API', function () {
    $owner = User::factory()->create();
    $headers = apiAuthHeader($owner);

    $created = $this->postJson('/api/v1/training/links', ['title' => 'Ironman Cozumel', 'expires_in_days' => 30], $headers)
        ->assertCreated()
        ->assertJsonPath('data.title', 'Ironman Cozumel')
        ->assertJsonPath('data.revoked_at', null)
        ->assertJsonPath('data.messages_count', 0);

    $link = CheerLink::query()->sole();
    expect($created->json('data.url'))->toBe(rtrim((string) config('app.url'), '/').'/animo/'.$link->token)
        ->and($link->token)->toHaveLength(32)
        ->and($link->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $this->postJson('/api/v1/training/links', ['title' => 'Sin vencer', 'expires_in_days' => null], $headers)
        ->assertCreated()->assertJsonPath('data.expires_at', null);
    $this->postJson('/api/v1/training/links', ['title' => 'Mal', 'expires_in_days' => 12], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('expires_in_days');
    $this->postJson('/api/v1/training/links', ['title' => str_repeat('a', 81)], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('title');

    $list = $this->getJson('/api/v1/training/links', $headers)->assertOk()->assertJsonCount(2, 'data');
    expect(array_keys($list->json('data.0')))->toEqualCanonicalizing(['uuid', 'title', 'url', 'expires_at', 'revoked_at', 'messages_count']);

    $this->deleteJson("/api/v1/training/links/{$link->uuid}", [], $headers)->assertNoContent();
    expect($link->fresh()->revoked_at)->not->toBeNull();

    $this->app['auth']->forgetGuards();
    $this->get('/animo/'.$link->token)->assertInertia(fn ($page) => $page->where('state', 'revoked'));
});

test('the athlete web page lists links and messages and its actions are owner-scoped', function () {
    $link = cheerLinkFor();
    $owner = $link->user;
    $message = sendCheer($link);

    $this->actingAs($owner)->get('/mi-entrenamiento')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('training/Index')
            ->where('filter', 'all')
            ->has('links', 1)
            ->where('links.0.state', 'active')
            ->has('messages.data', 1)
            ->where('counts.unheard', 1));

    $this->actingAs($owner)->post("/mi-entrenamiento/mensajes/{$message->uuid}/favorito", ['favorite' => true])->assertRedirect();
    $this->actingAs($owner)->post("/mi-entrenamiento/mensajes/{$message->uuid}/escuchado")->assertRedirect();
    expect($message->fresh()->favorite)->toBeTrue()->and($message->fresh()->listened_at)->not->toBeNull();

    $this->actingAs($owner)->post('/mi-entrenamiento/enlaces', ['title' => 'Medio maratón', 'expires_in_days' => 7])->assertRedirect();
    expect(CheerLink::query()->where('user_id', $owner->id)->count())->toBe(2);

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->delete("/mi-entrenamiento/mensajes/{$message->uuid}")->assertNotFound();
    $this->actingAs($stranger)->delete("/mi-entrenamiento/enlaces/{$link->uuid}")->assertNotFound();

    $this->actingAs($owner)->delete("/mi-entrenamiento/mensajes/{$message->uuid}")->assertRedirect();
    expect($message->fresh()->status)->toBe(CheerMessageStatus::Hidden);
});

test('guests are sent to log in from /mi-entrenamiento', function () {
    $this->get('/mi-entrenamiento')->assertRedirect();
});
