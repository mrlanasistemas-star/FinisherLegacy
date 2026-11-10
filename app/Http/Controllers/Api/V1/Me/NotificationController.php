<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPresenter;
use App\Support\Notifications\UnreadCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * `GET me/notifications`, `POST me/notifications/{id}/read`, `POST
 * me/notifications/read-all` — the same database notifications the web
 * center (App\Http\Controllers\NotificationController) reads, so the app and
 * the web always show the same items and the same read state. Admin alerts
 * (App\Notifications\AdminAlert) live in the admin bell and are excluded.
 */
class NotificationController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $query = $request->query('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications();

        $notifications = UnreadCounter::customer($query)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $notifications->through(fn (DatabaseNotification $n) => NotificationPresenter::apiRow($n));

        return $this->respond($notifications, meta: ['unread_count' => $this->unread($request)]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->sanctumUser($request)->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return $this->respond(['unread_count' => $this->unread($request)], __('Notificación marcada como leída.'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UnreadCounter::customer($this->sanctumUser($request)->unreadNotifications())->update(['read_at' => now()]);

        return $this->respond(['unread_count' => 0], __('Todas las notificaciones fueron marcadas como leídas.'));
    }

    private function unread(Request $request): int
    {
        return UnreadCounter::customer($this->sanctumUser($request)->unreadNotifications())->count();
    }
}
