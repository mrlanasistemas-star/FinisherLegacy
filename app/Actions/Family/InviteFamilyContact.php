<?php

namespace App\Actions\Family;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\FamilyLinkStatus;
use App\Enums\NotificationType;
use App\Models\AthleteFamilyLink;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Invitar a mi familia". Two ways (docs/renovacion/familia.md):
 *
 *  - by the email of an existing account → the row points at that user
 *    right away and they get an in-app + push invitation to accept;
 *  - by a shareable link → returns the plaintext token ONCE (only its
 *    SHA-256 is stored, 7-day lifetime); whoever opens it signed in and
 *    accepts becomes the contact.
 *
 * Either way the link starts `pending` and grants nothing until accepted —
 * and even then, only explicit tracking shares show any location.
 */
class InviteFamilyContact
{
    /** Cap of open (pending + accepted) links per athlete — spam guard. */
    public const int MAX_OPEN_LINKS = 30;

    public const int INVITE_TTL_DAYS = 7;

    public function __construct(private readonly SendAthleteNotification $notify) {}

    /**
     * @return array{link: AthleteFamilyLink, token: ?string, url: ?string}
     */
    public function handle(User $athlete, ?string $email, ?string $label): array
    {
        if (AthleteFamilyLink::query()->where('athlete_user_id', $athlete->id)->open()->count() >= self::MAX_OPEN_LINKS) {
            throw ValidationException::withMessages(['email' => __('Llegaste al máximo de :count personas en tu familia.', ['count' => self::MAX_OPEN_LINKS])]);
        }

        $label = $label !== null ? (trim($label) ?: null) : null;

        return $email !== null && trim($email) !== ''
            ? $this->byEmail($athlete, trim($email), $label)
            : $this->byLink($athlete, $label);
    }

    /** @return array{link: AthleteFamilyLink, token: null, url: null} */
    private function byEmail(User $athlete, string $email, ?string $label): array
    {
        $contact = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if ($contact === null) {
            throw ValidationException::withMessages(['email' => __('No encontramos una cuenta con ese correo. Puedes enviarle un enlace de invitación.')]);
        }

        if ($contact->is($athlete)) {
            throw ValidationException::withMessages(['email' => __('No puedes invitarte a ti mismo.')]);
        }

        $alreadyOpen = AthleteFamilyLink::query()
            ->where('athlete_user_id', $athlete->id)
            ->where('contact_user_id', $contact->id)
            ->open()
            ->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages(['email' => __('Esta persona ya está en tu familia o tiene una invitación pendiente.')]);
        }

        $link = AthleteFamilyLink::create([
            'uuid' => (string) Str::uuid(),
            'athlete_user_id' => $athlete->id,
            'contact_user_id' => $contact->id,
            'label' => $label,
            'status' => FamilyLinkStatus::Pending,
        ]);

        $name = $athlete->name;
        $this->notify->handle(
            $contact,
            fn () => __(':name te invitó a su familia', ['name' => $name]),
            fn () => __('Acepta para poder seguir su ubicación cuando la comparta contigo en sus carreras y entrenamientos.'),
            NotificationType::FamilyInvite,
            metadata: ['target' => ['type' => 'family', 'uuid' => $link->uuid]],
            push: true,
        );

        return ['link' => $link, 'token' => null, 'url' => null];
    }

    /** @return array{link: AthleteFamilyLink, token: string, url: string} */
    private function byLink(User $athlete, ?string $label): array
    {
        $token = Str::random(48);

        $link = AthleteFamilyLink::create([
            'uuid' => (string) Str::uuid(),
            'athlete_user_id' => $athlete->id,
            'invite_token_hash' => AthleteFamilyLink::hashToken($token),
            'invite_expires_at' => now()->addDays(self::INVITE_TTL_DAYS),
            'label' => $label,
            'status' => FamilyLinkStatus::Pending,
        ]);

        return ['link' => $link, 'token' => $token, 'url' => route('family.invite.show', ['token' => $token])];
    }
}
