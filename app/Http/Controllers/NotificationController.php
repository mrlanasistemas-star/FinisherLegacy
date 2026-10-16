<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification center behind the header bell (product UX consolidation
 * brief §37, §59; spec §69–74) — this page plus the bell dropdown, never a
 * sidebar module. Every row came from App\Notifications\AthleteAlert (via
 * SendAthleteNotification). Realtime (`notification.created`) only nudges
 * the UI; this controller stays the source of truth.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filtro') === 'sin-leer' ? 'unread' : 'all';
        $user = $request->user();

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        $notifications = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => NotificationPresenter::row($n));

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Bell dropdown + soft polling fallback when realtime is off. Small and
     * cheap: one indexed count + the 6 latest rows.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (DatabaseNotification $n) => NotificationPresenter::row($n))
                ->all(),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread_count' => 0]);
        }

        return back();
    }
}
