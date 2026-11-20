<?php

namespace App\Support\Family;

use App\Models\AthleteFamilyLink;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * JSON shapes of the family feature (API + Inertia), in one place so the
 * app and the web never disagree. Only what the other side needs: a name,
 * an avatar and the public @username — never the other person's email.
 */
final class FamilyPresenter
{
    /** @return array{name: string, username: ?string, avatar_url: ?string} */
    public static function person(?User $user): array
    {
        $profile = $user?->athleteProfile;
        $path = $profile?->profile_photo_path;

        return [
            'name' => $user !== null ? (trim((string) $user->name) ?: __('Atleta')) : __('Invitación pendiente'),
            'username' => $profile?->username,
            'avatar_url' => $path ? Storage::disk('public')->url($path) : null,
        ];
    }

    /**
     * A link as its owner (the athlete) sees it.
     *
     * @return array<string, mixed>
     */
    public static function forAthlete(AthleteFamilyLink $link): array
    {
        return [
            'uuid' => $link->uuid,
            'label' => $link->label,
            'status' => $link->status->value,
            // `link` = waiting for someone to open a shared invite link.
            'kind' => $link->contact_user_id === null ? 'link' : 'account',
            'contact' => $link->contact_user_id !== null ? self::person($link->contactUser) : null,
            'invite_expires_at' => $link->invite_expires_at?->toIso8601String(),
            'accepted_at' => $link->accepted_at?->toIso8601String(),
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    /**
     * A link as the invited person sees it (invitation or membership). The
     * athlete's label ("Mamá") is shown to them too — it names the
     * relationship, it is not a secret.
     *
     * @return array<string, mixed>
     */
    public static function forContact(AthleteFamilyLink $link): array
    {
        return [
            'uuid' => $link->uuid,
            'label' => $link->label,
            'status' => $link->status->value,
            'athlete' => self::person($link->athleteUser),
            'accepted_at' => $link->accepted_at?->toIso8601String(),
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }
}
