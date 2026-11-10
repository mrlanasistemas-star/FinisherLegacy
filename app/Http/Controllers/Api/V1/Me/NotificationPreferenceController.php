<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `GET|PUT /api/v1/me/notifications/preferences` — the per-category in-app +
 * push switches of Ajustes › Notificaciones in the app. Same storage and
 * rules as the web page (App\Http\Controllers\Settings\NotificationPreferenceController),
 * enforced server-side by SendAthleteNotification / PushSender.
 *
 * PUT body: `{"preferences": {"comunidad": {"in_app": true, "push": false}, …}}`
 * (partial updates allowed).
 */
class NotificationPreferenceController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function show(Request $request, NotificationPreferences $preferences, PushSender $push): JsonResponse
    {
        return $this->respond($this->payload($request, $preferences, $push));
    }

    public function update(Request $request, NotificationPreferences $preferences, PushSender $push): JsonResponse
    {
        $keys = array_map(fn (NotificationCategory $c) => $c->value, NotificationCategory::cases());

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.push' => ['sometimes', 'boolean'],
        ]);

        $unknown = array_diff(array_keys($data['preferences']), $keys);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['preferences' => 'Categoría de notificación desconocida.']);
        }

        $preferences->updateCustomer($this->sanctumUser($request), $data['preferences']);

        return $this->respond($this->payload($request, $preferences, $push), 'Preferencias guardadas.');
    }

    /**
     * @return array{categories: list<array<string, mixed>>, push_available: bool}
     */
    private function payload(Request $request, NotificationPreferences $preferences, PushSender $push): array
    {
        return [
            'categories' => $preferences->customerMatrix($this->sanctumUser($request)),
            'push_available' => $push->isConfigured(),
        ];
    }
}
