<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AthleteAlert;
use App\Support\I18n\RecipientLocale;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\NotificationTarget;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushSender;
use Closure;
use Illuminate\Support\Str;

/**
 * The one Action every customer notification goes through — admin forms,
 * the API, social activity, cheers, orders, tags, support, broadcasts
 * (docs/renovacion/notificaciones.md). One call = the in-app row (web
 * /notifications + app Notificaciones, same table, read state shared through
 * the API) + realtime event + push to every active device when `$push`.
 *
 * Preferences (NotificationPreferences, per category = `$type->category()`
 * unless `$category` overrides it) are enforced here, server-side:
 *  - in-app OFF → no row (an admin-sent message, `$sentBy`, always saves);
 *  - push OFF   → no push.
 *
 * Language: a system-written title/message is passed as a closure —
 * `fn () => __('Tu pedido :number va en camino', ['number' => $n])` — and is
 * rendered here in the RECIPIENT's saved language (the application default
 * when they never chose one), never the sender's or the worker's. The row,
 * the realtime event and the push all carry that same rendered text. A plain
 * string (something a person typed: an admin message, a campaign) is sent as
 * written.
 *
 * Returns the stored notification id (null when the in-app row was skipped).
 */
class SendAthleteNotification
{
    public function __construct(
        private readonly PushSender $pushSender,
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * @param  string|Closure(): string  $title
     * @param  string|Closure(): string  $message
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $recipient,
        string|Closure $title,
        string|Closure $message,
        NotificationType $type = NotificationType::Custom,
        ?string $actionUrl = null,
        array $metadata = [],
        ?User $sentBy = null,
        bool $push = false,
        ?string $imageUrl = null,
        ?NotificationCategory $category = null,
    ): ?string {
        $title = RecipientLocale::text($recipient, $title);
        $message = RecipientLocale::text($recipient, $message);
        $category ??= $type->category();
        $notificationId = null;

        if ($sentBy !== null || $this->preferences->allows($recipient, $category, 'in_app')) {
            $alert = new AthleteAlert($title, $message, $type, $actionUrl, [...$metadata, 'category' => $category->value], $sentBy, $imageUrl);
            // Fixed id up front: the database row, the realtime event and the
            // push `data.notification_id` must all carry the same one (a queued
            // notification otherwise only gets its id on an internal clone).
            $alert->id = (string) Str::uuid();
            $notificationId = $alert->id;

            $recipient->notify($alert);
        }

        // An admin-sent notification is audited here once, regardless of
        // whether the caller was Web or API — never the body, never a token.
        if ($sentBy !== null) {
            activity()
                ->causedBy($sentBy)
                ->performedOn($recipient)
                ->withProperties(['type' => $type->value, 'push_requested' => $push])
                ->log('notification_sent');
        }

        if ($push) {
            // Mobile push-tap routing contract: exactly
            // {notification_id, type, target, action_url} — target is
            // {type, id|uuid, slug?, event_slug?} (NotificationTarget).
            $this->pushSender->sendToUser($recipient, new PushMessage(
                title: $title,
                body: $message,
                data: [
                    'notification_id' => $notificationId,
                    'type' => $type->value,
                    'target' => NotificationTarget::fromMetadata($metadata),
                    'action_url' => $actionUrl,
                ],
                deepLink: $actionUrl,
                imageUrl: $imageUrl,
                category: $category,
            ));
        }

        return $notificationId;
    }
}
