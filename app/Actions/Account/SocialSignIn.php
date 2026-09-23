<?php

namespace App\Actions\Account;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\UserStatus;
use App\Exceptions\SocialAuthUnavailableException;
use App\Models\User;
use App\Models\UserSocialAccount;
use App\Services\Auth\IdTokenVerifier;
use App\Services\LegacyIdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Native Google / Apple sign-in → one Finisher Legacy account → the same
 * Sanctum token as password login (never a second session type).
 *
 * Resolution order:
 *  1. An existing link (provider + `sub`) → that user.
 *  2. A provider-VERIFIED email matching an existing user → link it.
 *  3. Otherwise a new user (athlete role, Legacy ID, Athlete identity —
 *     same as registration).
 *
 * Apple may hide the email (private relay) or only send it the first time;
 * with no email at all a non-routable placeholder keeps `users.email`
 * unique, and the account still works through the provider link.
 */
class SocialSignIn
{
    public function __construct(
        private readonly IdTokenVerifier $verifier,
        private readonly EnsureAthleteForUser $ensureAthlete,
        private readonly LegacyIdService $legacyIds,
    ) {}

    /**
     * @param  array{given_name?: string|null, family_name?: string|null}  $profile  Name the app received natively (Apple sends it only once).
     * @return array{user: User, created: bool}
     */
    public function handle(string $provider, string $idToken, array $profile = [], ?string $nonce = null): array
    {
        /** @var array{client_ids: list<string>, jwks_url: string, issuers: list<string>}|null $config */
        $config = config("finisher.social_auth.{$provider}");

        if (! is_array($config) || $config['client_ids'] === []) {
            throw new SocialAuthUnavailableException($provider);
        }

        $claims = $this->verifier->verify($idToken, $config, $nonce);
        $subject = (string) $claims['sub'];
        $email = isset($claims['email']) && is_string($claims['email']) ? Str::lower($claims['email']) : null;
        $emailVerified = in_array($claims['email_verified'] ?? false, [true, 'true'], true);
        $isPrivateRelay = in_array($claims['is_private_email'] ?? false, [true, 'true'], true);

        return DB::transaction(function () use ($provider, $subject, $email, $emailVerified, $isPrivateRelay, $claims, $profile) {
            $link = UserSocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $subject)
                ->with('user')
                ->first();

            if ($link !== null && $link->user !== null) {
                $link->update(['last_used_at' => now(), 'email' => $email ?? $link->email]);

                return ['user' => $link->user, 'created' => false];
            }

            $link?->delete(); // stale link to a deleted account

            $user = $email !== null && $emailVerified
                ? User::query()->where('email', $email)->first()
                : null;

            $created = $user === null;

            if ($created) {
                $user = $this->createUser($email, $emailVerified, $claims, $profile, $provider, $subject);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $subject,
                'email' => $email,
                'email_is_private_relay' => $isPrivateRelay,
                'last_used_at' => now(),
            ]);

            return ['user' => $user, 'created' => $created];
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array{given_name?: string|null, family_name?: string|null}  $profile
     */
    private function createUser(?string $email, bool $emailVerified, array $claims, array $profile, string $provider, string $subject): User
    {
        $firstName = trim((string) ($profile['given_name'] ?? $claims['given_name'] ?? '')) ?: 'Atleta';
        $lastName = trim((string) ($profile['family_name'] ?? $claims['family_name'] ?? '')) ?: 'Finisher';

        $user = User::create([
            'first_name' => Str::limit($firstName, 255, ''),
            'last_name' => Str::limit($lastName, 255, ''),
            'email' => $email ?? "{$provider}-".substr(hash('sha256', $subject), 0, 24).'@users.finisherlegacy.invalid',
            // Unusable random password — this account signs in through the
            // provider (and can set a password later via "forgot password"
            // if it has a real email).
            'password' => Str::random(64),
        ]);

        $user->forceFill([
            'status' => UserStatus::Active,
            'email_verified_at' => $email !== null && $emailVerified ? now() : null,
        ])->save();

        $user->assignRole('athlete');
        $this->legacyIds->issueFor($user);
        $this->ensureAthlete->handle($user, 'social_sign_in');

        return $user;
    }
}
