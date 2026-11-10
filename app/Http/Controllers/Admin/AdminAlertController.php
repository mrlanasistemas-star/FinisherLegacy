<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminAlertCategory;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\NotificationPresenter;
use App\Support\Notifications\PushSender;
use App\Support\Notifications\UnreadCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración › Alertas (docs/renovacion/notificaciones.md §Alertas admin):
 * the admin topbar bell (`summary`, read/read-all), the full list and each
 * admin's per-category email/push choices. Only App\Notifications\AdminAlert
 * rows — the personal notification center never shows them.
 */
class AdminAlertController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = AdminAlertCategory::tryFrom($request->string('categoria')->toString());
        $unreadOnly = $request->query('filtro') === 'sin-leer';

        $query = UnreadCounter::admin($unreadOnly ? $user->unreadNotifications() : $user->notifications())
            ->when($category !== null, fn ($q) => $q->where('data->category', $category?->value));

        return Inertia::render('admin/Alerts', [
            'alerts' => $query->latest()->paginate(30)->withQueryString()->through(fn (DatabaseNotification $n) => self::row($n)),
            'filters' => ['categoria' => $category?->value, 'filtro' => $unreadOnly ? 'sin-leer' : null],
            'categories' => array_map(fn (AdminAlertCategory $c) => ['value' => $c->value, 'label' => $c->label()], AdminAlertCategory::cases()),
            'unreadCount' => UnreadCounter::admin($user->unreadNotifications())->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => UnreadCounter::admin($user->unreadNotifications())->count(),
            'latest' => UnreadCounter::admin($user->notifications())
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $n) => self::row($n))
                ->all(),
        ]);
    }

    public function markRead(Request $request, string $alert): JsonResponse|RedirectResponse
    {
        UnreadCounter::admin($request->user()->notifications())->whereKey($alert)->firstOrFail()->markAsRead();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread_count' => UnreadCounter::admin($request->user()->unreadNotifications())->count()]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        UnreadCounter::admin($request->user()->unreadNotifications())->update(['read_at' => now()]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread_count' => 0]);
        }

        return back();
    }

    public function preferences(Request $request, NotificationPreferences $preferences, PushSender $push): Response
    {
        $user = $request->user();

        return Inertia::render('admin/AlertPreferences', [
            'categories' => $preferences->adminMatrix($user),
            'pushAvailable' => $push->isConfigured(),
            'devicesCount' => $user->pushDevices()->where('active', true)->count(),
            'email' => $user->email,
        ]);
    }

    public function updatePreferences(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $keys = array_map(fn (AdminAlertCategory $c) => $c->value, AdminAlertCategory::cases());

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.key' => ['required', 'string', Rule::in($keys)],
            'preferences.*.email' => ['required', 'boolean'],
            'preferences.*.push' => ['required', 'boolean'],
        ]);

        $values = [];

        foreach ($data['preferences'] as $row) {
            $values[$row['key']] = ['email' => $row['email'], 'push' => $row['push']];
        }

        $preferences->updateAdmin($request->user(), $values);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferencias de alertas guardadas.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            ...NotificationPresenter::row($notification),
            'category' => $data['category'] ?? null,
            'category_label' => AdminAlertCategory::tryFrom((string) ($data['category'] ?? ''))?->label(),
            'level' => $data['level'] ?? 'info',
        ];
    }
}
