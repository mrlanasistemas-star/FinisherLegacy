<?php

namespace App\Actions\Family;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\FamilyLinkStatus;
use App\Enums\NotificationType;
use App\Models\AthleteFamilyLink;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Accept or decline a pending family invitation — either one addressed to
 * `$contact` by email, or a link-invite that `$contact` just opened (it
 * becomes theirs on accept). The token hash is cleared either way, so an
 * invite link works exactly once.
 */
class RespondToFamilyInvite
{
    public function __construct(private readonly SendAthleteNotification $notify) {}

    public function accept(AthleteFamilyLink $link, User $contact): AthleteFamilyLink
    {
        $this->guard($link, $contact);

        $duplicate = AthleteFamilyLink::query()
            ->where('athlete_user_id', $link->athlete_user_id)
            ->where('contact_user_id', $contact->id)
            ->whereKeyNot($link->id)
            ->accepted()
            ->exists();

        if ($duplicate) {
            // Already family through another link: this invite is simply used up.
            $link->update(['status' => FamilyLinkStatus::Declined, 'declined_at' => now(), 'invite_token_hash' => null]);

            throw ValidationException::withMessages(['invite' => __('Ya formas parte de la familia de esta persona.')]);
        }

        $link->update([
            'contact_user_id' => $contact->id,
            'status' => FamilyLinkStatus::Accepted,
            'accepted_at' => now(),
            'invite_token_hash' => null,
        ]);

        $athlete = $link->athleteUser()->first();
        if ($athlete !== null) {
            $name = $contact->name;
            $this->notify->handle(
                $athlete,
                fn () => __(':name aceptó tu invitación', ['name' => $name]),
                fn () => __('Ya puedes compartirle tu ubicación en vivo cuando corras o entrenes.'),
                NotificationType::FamilyAccepted,
                metadata: ['target' => ['type' => 'family', 'uuid' => $link->uuid]],
                push: true,
            );
        }

        return $link;
    }

    public function decline(AthleteFamilyLink $link, User $contact): AthleteFamilyLink
    {
        $this->guard($link, $contact);

        // A declined link-invite stays anonymous: nobody gets attached to it.
        $link->update([
            'status' => FamilyLinkStatus::Declined,
            'declined_at' => now(),
            'invite_token_hash' => null,
        ]);

        return $link;
    }

    private function guard(AthleteFamilyLink $link, User $contact): void
    {
        if ($link->status !== FamilyLinkStatus::Pending) {
            throw ValidationException::withMessages(['invite' => __('Esta invitación ya no está disponible.')]);
        }

        if ($link->athlete_user_id === $contact->id) {
            throw ValidationException::withMessages(['invite' => __('No puedes aceptar tu propia invitación.')]);
        }

        // An email invite belongs to that one account only.
        if ($link->contact_user_id !== null && $link->contact_user_id !== $contact->id) {
            throw ValidationException::withMessages(['invite' => __('Esta invitación es para otra cuenta.')]);
        }
    }
}
