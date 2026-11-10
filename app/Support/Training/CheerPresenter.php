<?php

namespace App\Support\Training;

use App\Enums\ProfileVisibility;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * One JSON shape per Entrenamientos object, shared by the web pages and
 * /api/v1/training (the mobile app codes against `message()` and `link()`
 * exactly — see docs/renovacion/entrenamientos.md). Audio only ever leaves
 * as a short-lived signed URL; the sender's IP hash never leaves at all.
 */
class CheerPresenter
{
    /**
     * @return array{uuid: string, sender_name: string, relationship: string|null, body: string|null, icon: string, audio_url: string|null, duration_seconds: int, listened_at: string|null, favorite: bool, created_at: string, link: array{uuid: string, title: string}}
     */
    public function message(CheerMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'sender_name' => $message->sender_name,
            'relationship' => $message->relationship,
            'body' => $message->body,
            'icon' => $message->icon->value,
            'audio_url' => $message->audioUrl(),
            'duration_seconds' => $message->duration_seconds,
            'listened_at' => $message->listened_at?->toIso8601String(),
            'favorite' => $message->favorite,
            'created_at' => $message->created_at->toIso8601String(),
            'link' => ['uuid' => $message->link->uuid, 'title' => $message->link->title],
        ];
    }

    /**
     * @return array{uuid: string, title: string, url: string, expires_at: string|null, revoked_at: string|null, messages_count: int}
     */
    public function link(CheerLink $link): array
    {
        return [
            'uuid' => $link->uuid,
            'title' => $link->title,
            'url' => $link->url(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'revoked_at' => $link->revoked_at?->toIso8601String(),
            'messages_count' => $link->messages_count,
        ];
    }

    /**
     * Web-only extras (state badge, cap).
     *
     * @return array<string, mixed>
     */
    public function linkForWeb(CheerLink $link): array
    {
        return [
            ...$this->link($link),
            'state' => $link->state(),
            'max_messages' => $link->max_messages,
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    /**
     * What a sender sees about the athlete: account display name, and the
     * profile photo only when the athlete's profile is public.
     *
     * @return array{name: string, avatar_url: string|null}
     */
    public function athlete(User $user): array
    {
        $profile = $user->athleteProfile;
        $photo = $profile !== null
            && $profile->profile_visibility === ProfileVisibility::Public
            && is_string($profile->profile_photo_path) && $profile->profile_photo_path !== ''
                ? Storage::disk('public')->url($profile->profile_photo_path)
                : null;

        return ['name' => trim((string) $user->name) ?: __('Atleta'), 'avatar_url' => $photo];
    }
}
