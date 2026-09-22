<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Notifications\SendAthleteNotification;
use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\NotificationType;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendAdminNotificationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The REST equivalent of the Web "Comunicación" send button
 * (App\Http\Controllers\Admin\ParticipantController::notify /
 * Admin\AthleteController::notify) — same App\Actions\Notifications\
 * SendAthleteNotification, gated by the same `notifications.send`
 * permission (consolidation brief §36-§40). Never constructs
 * App\Notifications\AthleteAlert or dispatches push itself; that stays
 * inside the Action so Web and API can never drift.
 */
class NotificationController extends Controller
{
    use ApiResponses;

    public function store(SendAdminNotificationRequest $request, User $user, SendAthleteNotification $send, PushNotificationGateway $pushGateway): JsonResponse
    {
        $push = $request->boolean('push');

        $send->handle(
            recipient: $user,
            title: $request->string('title')->toString(),
            message: $request->string('message')->toString(),
            type: NotificationType::from($request->string('type')->toString()),
            actionUrl: $request->string('action_url')->toString() ?: null,
            sentBy: ($request->user('sanctum') ?? $request->user()),
            push: $push,
        );

        return $this->respond([
            'push_requested' => $push,
            'push_available' => $pushGateway->isConfigured(),
        ], 'Notificación creada.', status: 201);
    }
}
