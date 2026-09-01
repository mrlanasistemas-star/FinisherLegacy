<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AthleteAlert;

/**
 * The one place "send an athlete a notification" happens (product UX
 * consolidation brief §36-§38, §52) — Web (participant profile,
 * preregistrations) and the future admin bulk-reminder flow all call this
 * instead of constructing App\Notifications\AthleteAlert by hand.
 */
class SendAthleteNotification
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $recipient,
        string $title,
        string $message,
        NotificationType $type = NotificationType::Custom,
        ?string $actionUrl = null,
        array $metadata = [],
        ?User $sentBy = null,
    ): void {
        $recipient->notify(new AthleteAlert($title, $message, $type, $actionUrl, $metadata, $sentBy));
    }
}
