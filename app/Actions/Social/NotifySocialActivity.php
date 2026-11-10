<?php

namespace App\Actions\Social;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\LegacyMoment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Notifications for the social layer (new follower, reaction, comment):
 * in-app row + push (category Comunidad — each person can turn either off in
 * Ajustes › Notificaciones). `metadata` carries the structured target so the
 * app deep-links without parsing URLs: `{kind: athlete|moment, username?,
 * moment_uuid?}`. A reaction toggled off and on again pushes at most once per
 * hour per (moment, person) — the in-app row still records it. Texts are
 * closures: SendAthleteNotification renders them in the recipient's language.
 */
class NotifySocialActivity
{
    public function __construct(private readonly SendAthleteNotification $send) {}

    public function newFollower(User $recipient, User $follower): void
    {
        $username = $follower->athleteProfile?->username;

        $this->send->handle(
            $recipient,
            fn (): string => __('Nuevo seguidor'),
            fn (): string => __(':name empezó a seguir tu Legacy.', ['name' => $follower->name]),
            NotificationType::NewFollower,
            $username !== null ? $this->profileUrl($username) : null,
            ['kind' => 'athlete', 'username' => $username],
            push: Cache::add("push:follow:{$recipient->id}:{$follower->id}", 1, now()->addDay()),
        );
    }

    public function momentReaction(LegacyMoment $moment, User $reactor, string $type): void
    {
        $this->send->handle(
            $moment->author,
            fn (): string => __('Nueva reacción'),
            fn (): string => $type === 'cheer'
                ? __(':name te echó porras en tu momento.', ['name' => $reactor->name])
                : __(':name reaccionó a tu momento.', ['name' => $reactor->name]),
            NotificationType::MomentReaction,
            $this->momentUrl($moment),
            ['kind' => 'moment', 'moment_uuid' => $moment->uuid, 'username' => $reactor->athleteProfile?->username],
            push: Cache::add("push:reaction:{$moment->id}:{$reactor->id}", 1, now()->addHour()),
        );
    }

    public function momentComment(LegacyMoment $moment, User $commenter, string $body): void
    {
        $this->send->handle(
            $moment->author,
            fn (): string => __('Nuevo mensaje de apoyo'),
            "{$commenter->name}: ".mb_strimwidth($body, 0, 90, '…'),
            NotificationType::MomentComment,
            $this->momentUrl($moment),
            ['kind' => 'moment', 'moment_uuid' => $moment->uuid, 'username' => $commenter->athleteProfile?->username],
            push: true,
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
