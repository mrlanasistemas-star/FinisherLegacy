<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * `GET me/notifications`, `POST me/notifications/{id}/read`, `POST
 * me/notifications/read-all` — same Laravel database notifications
 * App\Http\Controllers\NotificationController (Web) already reads, never
 * a second notification system (product consolidation brief §31).
 */
class NotificationController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? null,
            'message' => $n->data['message'] ?? null,
            'type' => $n->data['type'] ?? null,
            'action_url' => $n->data['action_url'] ?? null,
            // Structured deep-link target for social notifications
            // (`{kind: athlete|moment, username?, moment_uuid?}`) — lets the
            // app route without parsing web URLs.
            'target' => $this->target($n->data['metadata'] ?? null),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return $this->respond($notifications, meta: ['unread_count' => $user->unreadNotifications()->count()]);
    }

    /**
     * Only the whitelisted routing keys — never the rest of `metadata`,
     * which may hold admin-side context.
     *
     * @return array<string, string>|null
     */
    private function target(mixed $metadata): ?array
    {
        if (! is_array($metadata) || ! in_array($metadata['kind'] ?? null, ['athlete', 'moment', 'order', 'event'], true)) {
            return null;
        }

        return array_filter([
            'kind' => $metadata['kind'],
            'username' => is_string($metadata['username'] ?? null) ? $metadata['username'] : null,
            'moment_uuid' => is_string($metadata['moment_uuid'] ?? null) ? $metadata['moment_uuid'] : null,
            'order_uuid' => is_string($metadata['order_uuid'] ?? null) ? $metadata['order_uuid'] : null,
        ], fn ($value) => $value !== null);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->sanctumUser($request)->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return $this->respond(null, 'Notificación marcada como leída.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->sanctumUser($request)->unreadNotifications()->update(['read_at' => now()]);

        return $this->respond(null, 'Todas las notificaciones fueron marcadas como leídas.');
    }
}
