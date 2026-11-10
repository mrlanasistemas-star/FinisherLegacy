<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes › Notificaciones (web): per category, show it in the notification
 * center (in-app) and/or send it to the phone (push). Same storage and
 * server-side rules as the app (GET|PUT /api/v1/me/notifications/preferences).
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, NotificationPreferences $preferences, PushSender $push): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'categories' => $preferences->customerMatrix($user),
            'pushAvailable' => $push->isConfigured(),
            'devicesCount' => $user->pushDevices()->where('active', true)->count(),
        ]);
    }

    public function update(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $keys = array_map(fn (NotificationCategory $c) => $c->value, NotificationCategory::cases());

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.key' => ['required', 'string', Rule::in($keys)],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.push' => ['required', 'boolean'],
        ]);

        $values = [];

        foreach ($data['preferences'] as $row) {
            $values[$row['key']] = ['in_app' => $row['in_app'], 'push' => $row['push']];
        }

        $preferences->updateCustomer($request->user(), $values);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferencias de notificaciones guardadas.')]);

        return back();
    }
}
