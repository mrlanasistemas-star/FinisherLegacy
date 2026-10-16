<?php

namespace App\Services;

use App\Enums\PreregistrationStatus;
use App\Models\EventEdition;
use App\Models\EventPreregistration;
use App\Models\EventRace;
use App\Models\User;
use App\Support\CodeGenerator;
use Illuminate\Support\Str;

/**
 * Shared by the web /events/{event}/preregister form and the
 * /api/v1/events/{edition}/preregister endpoint — token/QR issuance and the
 * "is registration even open" rule only live here.
 */
class PreregistrationService
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function isOpen(EventEdition $edition): bool
    {
        $now = now();

        if ($edition->registration_open_at && $now->lt($edition->registration_open_at)) {
            return false;
        }

        if ($edition->registration_close_at && $now->gt($edition->registration_close_at)) {
            return false;
        }

        return ! $edition->event_date->isPast();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(EventEdition $edition, EventRace $race, array $data, ?User $user): EventPreregistration
    {
        $token = CodeGenerator::unique(
            'PRE',
            fn (string $code) => EventPreregistration::query()->where('token', $code)->exists(),
        );

        return EventPreregistration::create([
            'event_edition_id' => $edition->id,
            'event_race_id' => $race->id,
            'user_id' => $user?->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'bib_number' => $data['bib_number'] ?? null,
            'token' => $token,
            'qr_token' => (string) Str::uuid(),
            'status' => PreregistrationStatus::Pending,
        ]);
    }

    public function publicUrl(EventPreregistration $preregistration): string
    {
        return route('preregistrations.show', $preregistration->token);
    }

    public function qrSvg(EventPreregistration $preregistration): string
    {
        return $this->qr->svg($this->publicUrl($preregistration));
    }

    /**
     * The confirmation page is reachable by anyone holding the link (it's
     * also what the QR encodes), so a viewer who isn't the account that
     * preregistered (or staff) only sees the first name, the last name's
     * initial and the bib's last two characters.
     *
     * @return array{first_name: string, last_name: string, bib_number: string|null, masked: bool}
     */
    public function identityFor(EventPreregistration $preregistration, ?User $viewer): array
    {
        $revealed = $viewer !== null && (
            ($preregistration->user_id !== null && $preregistration->user_id === $viewer->id)
            || $viewer->can('preregistrations.view')
        );

        if ($revealed) {
            return [
                'first_name' => $preregistration->first_name,
                'last_name' => $preregistration->last_name,
                'bib_number' => $preregistration->bib_number,
                'masked' => false,
            ];
        }

        $bib = $preregistration->bib_number;

        return [
            'first_name' => $preregistration->first_name,
            'last_name' => Str::upper(Str::substr((string) $preregistration->last_name, 0, 1)).'.',
            'bib_number' => match (true) {
                $bib === null || $bib === '' => null,
                Str::length($bib) <= 2 => '••',
                default => str_repeat('•', Str::length($bib) - 2).Str::substr($bib, -2),
            },
            'masked' => true,
        ];
    }
}
