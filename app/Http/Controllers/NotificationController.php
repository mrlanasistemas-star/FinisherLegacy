<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The athlete-facing notification inbox behind the header bell (product UX
 * consolidation brief §37, §59) — deliberately not a new sidebar module
 * (brief §36: "no crear un módulo enorme aislado"), just this one page
 * plus the bell. Every notification here came from
 * App\Actions\Notifications\SendAthleteNotification.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? null,
            'message' => $n->data['message'] ?? null,
            'type' => $n->data['type'] ?? null,
            'action_url' => $n->data['action_url'] ?? null,
            'read_at' => $n->read_at?->diffForHumans(),
            'created_at' => $n->created_at->diffForHumans(),
        ]);

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
