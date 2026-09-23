<?php

namespace App\Actions\Social;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\LegacyMoment;
use App\Models\User;

/**
 * In-app notifications for the social layer (new follower, reaction,
 * comment). Database channel only — push stays off until a real push
 * provider replaces NullPushNotificationGateway. `metadata` carries the
 * structured target so the app deep-links without parsing URLs:
 * `{kind: athlete|moment, username?, moment_uuid?}`.
 */
class NotifySocialActivity
{
    public function __construct(private readonly SendAthleteNotification $send) {}

    public function newFollower(User $recipient, User $follower): void
    {
        $username = $follower->athleteProfile?->username;

        $this->send->handle(
            $recipient,
            'Nuevo seguidor',
            "{$follower->name} empezó a seguir tu Legacy.",
            NotificationType::NewFollower,
            $username !== null ? $this->profileUrl($username) : null,
            ['kind' => 'athlete', 'username' => $username],
        );
    }

    public function momentReaction(LegacyMoment $moment, User $reactor, string $type): void
    {
        $verb = $type === 'cheer' ? 'te echó porras en' : 'reaccionó a';

        $this->send->handle(
            $moment->author,
            'Nueva reacción',
            "{$reactor->name} {$verb} tu momento.",
            NotificationType::MomentReaction,
            $this->momentUrl($moment),
            ['kind' => 'moment', 'moment_uuid' => $moment->uuid, 'username' => $reactor->athleteProfile?->username],
        );
    }

    public function momentComment(LegacyMoment $moment, User $commenter, string $body): void
    {
        $this->send->handle(
            $moment->author,
            'Nuevo mensaje de apoyo',
            "{$commenter->name}: ".mb_strimwidth($body, 0, 90, '…'),
            NotificationType::MomentComment,
            $this->momentUrl($moment),
            ['kind' => 'moment', 'moment_uuid' => $moment->uuid, 'username' => $commenter->athleteProfile?->username],
        );
    }

    private function profileUrl(string $username): string
    {
        return rtrim((string) config('app.url'), '/')."/@{$username}";
    }

    /**
     * Web mapping for /moments/{uuid} is documented in
     * docs/SOCIAL_ARCHITECTURE.md §URLs — the app routes by `metadata`.
     */
    private function momentUrl(LegacyMoment $moment): string
    {
        return rtrim((string) config('app.url'), '/')."/moments/{$moment->uuid}";
    }
}
