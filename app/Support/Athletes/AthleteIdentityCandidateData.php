<?php

namespace App\Support\Athletes;

use App\Models\EventParticipant;
use App\Models\User;

/**
 * What App\Actions\Athletes\ResolveAthleteIdentity and
 * App\Services\Athletes\AthleteIdentityMatcher operate on — raw values
 * plus their normalized form (App\Support\Athletes\AthleteIdentityNormalizer),
 * computed once here so nothing downstream re-normalizes inconsistently.
 */
final class AthleteIdentityCandidateData
{
    private function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $fullName,
        public readonly string $normalizedFirstName,
        public readonly string $normalizedLastName,
        public readonly string $normalizedFullName,
        public readonly ?string $email,
        public readonly ?string $normalizedEmail,
        public readonly bool $emailVerified,
        public readonly ?string $phone,
        public readonly ?string $normalizedPhone,
        public readonly ?string $birthDate,
        public readonly ?string $gender,
        public readonly ?string $country,
        public readonly ?string $externalProvider,
        public readonly ?string $externalConnectionId,
        public readonly ?string $externalSubjectId,
    ) {}

    /**
     * @param  array<string, mixed>  $data  first_name, last_name (required); email, phone,
     *                                      birth_date, gender, country, email_verified,
     *                                      external_provider, external_connection_id,
     *                                      external_subject_id (all optional)
     */
    public static function fromArray(array $data): self
    {
        $normalizer = new AthleteIdentityNormalizer;

        $firstName = trim((string) $data['first_name']);
        $lastName = trim((string) $data['last_name']);
        $fullName = trim("{$firstName} {$lastName}");
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        return new self(
            firstName: $firstName,
            lastName: $lastName,
            fullName: $fullName,
            normalizedFirstName: $normalizer->name($firstName),
            normalizedLastName: $normalizer->name($lastName),
            normalizedFullName: $normalizer->fullName($firstName, $lastName),
            email: $email !== null && $email !== '' ? $email : null,
            normalizedEmail: $normalizer->email($email),
            emailVerified: (bool) ($data['email_verified'] ?? false),
            phone: $phone !== null && $phone !== '' ? $phone : null,
            normalizedPhone: $normalizer->phone($phone),
            birthDate: $data['birth_date'] ?? null,
            gender: $data['gender'] ?? null,
            country: $data['country'] ?? null,
            externalProvider: $data['external_provider'] ?? null,
            externalConnectionId: $data['external_connection_id'] ?? null,
            externalSubjectId: $data['external_subject_id'] ?? null,
        );
    }

    public static function fromEventParticipant(EventParticipant $participant): self
    {
        return self::fromArray([
            'first_name' => $participant->first_name,
            'last_name' => $participant->last_name,
            'email' => $participant->email,
            'phone' => $participant->phone,
            'birth_date' => $participant->birth_date?->toDateString(),
            'gender' => $participant->gender,
        ]);
    }

    /**
     * A User's email counts as "verified" here only when Fortify actually
     * verified it (`email_verified_at`) — an unverified registration email
     * is still useful as a signal (EMAIL_EXACT), just not the top tier
     * (VERIFIED_EMAIL_AND_BIRTHDATE). See docs/adr/0004 §Confidence.
     */
    public static function fromUser(User $user): self
    {
        return self::fromArray([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->email_verified_at !== null,
        ]);
    }

    /**
     * A User whose email (and phone) nobody has verified yet — only the
     * name, which never auto-links on its own. Used for the placeholder
     * Athlete of an unverified account so an attacker-typed email can't
     * become a match key for future imports either.
     */
    public static function fromUserWithoutEmail(User $user): self
    {
        return self::fromArray([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birthDate,
            'gender' => $this->gender,
            'country' => $this->country,
        ];
    }

    public function hasExternalIdentity(): bool
    {
        return $this->externalProvider !== null && $this->externalSubjectId !== null;
    }
}
