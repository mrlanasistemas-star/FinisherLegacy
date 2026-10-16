<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\User;
use App\Support\Notifications\NotificationPresenter;

function notifyUser(User $user, string $title, ?string $url = null): void
{
    app(SendAthleteNotification::class)->handle($user, $title, 'Mensaje', NotificationType::Custom, $url);
}

test('the summary endpoint returns the unread count and the latest rows of the signed-in user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach (range(1, 8) as $i) {
        notifyUser($user, "Aviso {$i}");
    }
    notifyUser($other, 'Ajeno');

    $response = $this->actingAs($user)->getJson('/notifications/summary')->assertOk();

    expect($response->json('unread_count'))->toBe(8)
        ->and($response->json('latest'))->toHaveCount(6)
        ->and(collect($response->json('latest'))->pluck('title'))->not->toContain('Ajeno');
});

test('the summary endpoint requires authentication', function () {
    $this->getJson('/notifications/summary')->assertUnauthorized();
});

test('the center filters unread and exposes the unread count', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Leída');
    notifyUser($user, 'Pendiente');
    $user->notifications()->get()->firstWhere('data.title', 'Leída')->markAsRead();

    $this->actingAs($user)->get('/notifications?filtro=sin-leer')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications')
            ->where('filter', 'unread')
            ->where('unreadCount', 1)
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Pendiente'));
});

test('mark read / mark all read answer JSON for the bell', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Uno');
    notifyUser($user, 'Dos');
    $first = $user->notifications()->first();

    $this->actingAs($user)->postJson("/notifications/{$first->id}/read")
        ->assertOk()->assertJson(['unread_count' => 1]);

    $this->actingAs($user)->postJson('/notifications/read-all')
        ->assertOk()->assertJson(['unread_count' => 0]);
});

test('unsafe action urls are never handed to the browser and own absolute urls become paths', function () {
    config(['app.url' => 'https://finisherlegacy.test']);

    expect(NotificationPresenter::safeUrl('javascript:alert(1)'))->toBeNull()
        ->and(NotificationPresenter::safeUrl('//evil.example/x'))->toBeNull()
        ->and(NotificationPresenter::safeUrl('/dashboard'))->toBe('/dashboard')
        ->and(NotificationPresenter::safeUrl('https://finisherlegacy.test/@ana?x=1'))->toBe('/@ana?x=1')
        ->and(NotificationPresenter::safeUrl('https://example.com/a'))->toBe('https://example.com/a');
});
