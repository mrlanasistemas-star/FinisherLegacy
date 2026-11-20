<?php

namespace App\Services\Privacy;

use App\Contracts\Privacy\PinProtectable;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Exceptions\Privacy\ContentPinException;
use App\Models\AthleteEventMedia;
use App\Models\LegacyMoment;
use App\Models\LegacyStory;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ContentPinNotice;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Optional content PIN ("NIP") — the security model in one place
 * (docs/renovacion/nip.md).
 *
 * Storage. The PIN is 4–6 digits. It is HMAC'd with the APP_KEY and only that
 * digest is password-hashed (bcrypt/argon, Hash::make) into
 * `users.content_pin_hash`; a leaked database alone is not enough to brute
 * force the small PIN space offline. The PIN is never logged, flashed
 * (bootstrap/app.php dontFlash) or returned.
 *
 * Lockout. Every wrong PIN increments `content_pin_failed_attempts` under a
 * row lock (no parallel-guess race). From MAX_ATTEMPTS on, each failure locks
 * the PIN for 1, 2, 4 … 60 minutes (lockMinutes()); while locked the hash is
 * not even checked, so there is no oracle. A correct PIN resets the counter.
 * Routes add a per-user throttle on top.
 *
 * Unlock. A correct PIN on POST /me/pin/unlock mints a random 64-char grant,
 * stored server-side (cache, keyed by its SHA-256) for GRANT_TTL_SECONDS and
 * bound to the user, to the Sanctum token that asked for it and to the
 * current PIN "epoch" (a digest of the stored hash — new salt on every set),
 * so changing, resetting or disabling the PIN revokes every grant at once.
 * The app sends it back as the X-Content-Unlock header and keeps it in memory
 * only. The web keeps the same grant in the session instead.
 *
 * Reveal. reveals($item) is true only when the current request carries a
 * valid grant of the item's OWNER. Nobody else (other users, staff) can ever
 * hold one, so protected items are hidden from them by construction; signed
 * media URLs of protected files are only minted while revealed, carry a
 * `pin=1` marker and live MEDIA_URL_TTL_MINUTES (App\Services\Media\MediaUrlGenerator).
 *
 * Recovery. Forgotten PIN → reset with the account password, or — for
 * accounts without a known password (social sign-in) — with a 6-digit code
 * emailed to the account address (hashed, 15 min, 5 tries). Either way the
 * protected items stay protected, every grant is revoked and the owner gets a
 * security email.
 */
class ContentPin
{
    public const MIN_LENGTH = 4;

    public const MAX_LENGTH = 6;

    public const MAX_ATTEMPTS = 5;

    public const MAX_LOCK_MINUTES = 60;

    public const GRANT_TTL_SECONDS = 600;

    public const MEDIA_URL_TTL_MINUTES = 10;

    public const HEADER = 'X-Content-Unlock';

    public const RECOVERY_TTL_MINUTES = 15;

    private const SESSION_KEY = 'content_pin.grant';

    private const REQUEST_CACHE = 'content_pin.unlocked_user';

    /** @return list<string> */
    public static function rules(): array
    {
        return ['required', 'string', 'regex:/^\d{'.self::MIN_LENGTH.','.self::MAX_LENGTH.'}$/'];
    }

    public function isEnabled(User $user): bool
    {
        return $user->content_pin_hash !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(User $user, ?Request $request = null): array
    {
        $lockedUntil = $this->lockedUntil($user);
        $athleteId = $user->athlete()->value('id');

        return [
            'enabled' => $this->isEnabled($user),
            'unlocked' => $request !== null && $this->unlockedUser($request)?->is($user) === true,
            'locked_until' => $lockedUntil?->toIso8601String(),
            'retry_after' => $lockedUntil !== null ? max(1, (int) ceil(now()->diffInSeconds($lockedUntil, true))) : null,
            'failed_attempts' => (int) $user->content_pin_failed_attempts,
            'remaining_attempts' => max(0, self::MAX_ATTEMPTS - (int) $user->content_pin_failed_attempts),
            'max_attempts' => self::MAX_ATTEMPTS,
            'min_length' => self::MIN_LENGTH,
            'max_length' => self::MAX_LENGTH,
            'grant_ttl_seconds' => self::GRANT_TTL_SECONDS,
            'set_at' => $user->content_pin_set_at?->toIso8601String(),
            'protected_counts' => [
                'stories' => $athleteId === null ? 0 : LegacyStory::query()->where('athlete_id', $athleteId)->where('pin_protected', true)->count(),
                'moments' => LegacyMoment::query()->where('user_id', $user->id)->where('pin_protected', true)->count(),
                'media' => $athleteId === null ? 0 : AthleteEventMedia::query()->where('athlete_id', $athleteId)->where('pin_protected', true)->count(),
            ],
            'recovery' => [
                // Social sign-in accounts may not know their password: the
                // emailed code covers them.
                'password' => true,
                'email_code' => filled($user->email),
            ],
        ];
    }

    public function enable(User $user, string $pin): void
    {
        if ($this->isEnabled($user)) {
            throw ContentPinException::alreadySet();
        }

        $this->store($user, $pin);
    }

    /** Requires the current PIN (counted against the lockout). */
    public function change(User $user, string $currentPin, string $newPin): void
    {
        $this->verify($user, $currentPin);
        $this->store($user, $newPin);
    }

    /**
     * Turning protection off removes the flag from every item (they stay
     * private) and revokes every grant. Caller already re-authenticated
     * (verify() or the account password).
     */
    public function disable(User $user): void
    {
        $athleteId = $user->athlete()->value('id');

        DB::transaction(function () use ($user, $athleteId) {
            if ($athleteId !== null) {
                LegacyStory::query()->where('athlete_id', $athleteId)->where('pin_protected', true)->update(['pin_protected' => false]);
                AthleteEventMedia::query()->where('athlete_id', $athleteId)->where('pin_protected', true)->update(['pin_protected' => false]);
            }
            LegacyMoment::query()->where('user_id', $user->id)->where('pin_protected', true)->update(['pin_protected' => false]);

            $user->forceFill([
                'content_pin_hash' => null,
                'content_pin_set_at' => null,
                'content_pin_failed_attempts' => 0,
                'content_pin_locked_until' => null,
            ])->save();
        });

        $this->forgetRequestCache();
    }

    /** New PIN after a successful recovery check. Protected items stay protected. */
    public function reset(User $user, string $pin): void
    {
        $this->store($user, $pin);
        $this->notify($user, ContentPinNotice::RESET);
    }

    /**
     * Checks a PIN against the lockout. Throws ContentPinException (PIN_LOCKED
     * with retry_after, PIN_INVALID with remaining attempts, PIN_NOT_SET).
     */
    public function verify(User $user, string $pin): void
    {
        $outcome = DB::transaction(function () use ($user, $pin) {
            /** @var User $row */
            $row = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($row->content_pin_hash === null) {
                return ['error' => ContentPinException::notSet()];
            }

            $lockedUntil = $this->lockedUntil($row);
            if ($lockedUntil !== null) {
                return ['error' => ContentPinException::locked($lockedUntil)];
            }

            if (Hash::check($this->digest($pin), $row->content_pin_hash)) {
                $row->forceFill(['content_pin_failed_attempts' => 0, 'content_pin_locked_until' => null])->save();

                return ['error' => null, 'row' => $row];
            }

            $attempts = (int) $row->content_pin_failed_attempts + 1;
            $lock = $attempts >= self::MAX_ATTEMPTS ? now()->addMinutes(self::lockMinutes($attempts)) : null;
            $row->forceFill(['content_pin_failed_attempts' => $attempts, 'content_pin_locked_until' => $lock])->save();

            // Thrown after commit, otherwise the counter would roll back.
            return ['error' => $lock !== null
                ? ContentPinException::locked($lock)
                : ContentPinException::invalid(self::MAX_ATTEMPTS - $attempts),
                'row' => $row];
        });

        if (isset($outcome['row'])) {
            $user->setRawAttributes($outcome['row']->getAttributes(), true);
        }

        if ($outcome['error'] !== null) {
            throw $outcome['error'];
        }
    }

    /** 1, 2, 4 … minutes from the MAX_ATTEMPTS-th failure on, capped. */
    public static function lockMinutes(int $attempts): int
    {
        return (int) min(self::MAX_LOCK_MINUTES, 2 ** max(0, $attempts - self::MAX_ATTEMPTS));
    }

    /**
     * Mints an unlock grant for the API (token) or the web (session).
     *
     * @return array{unlock_token: string|null, expires_at: string, expires_in: int}
     */
    public function issueGrant(User $user, Request $request): array
    {
        $expiresAt = now()->addSeconds(self::GRANT_TTL_SECONDS);
        $epoch = $this->epoch($user);
        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $token = Str::random(64);
            Cache::put($this->grantKey($token), [
                'user_id' => $user->id,
                'token_id' => $accessToken->getKey(),
                'epoch' => $epoch,
            ], $expiresAt);
        } else {
            $token = null;
            $request->session()->put(self::SESSION_KEY, [
                'user_id' => $user->id,
                'epoch' => $epoch,
                'expires_at' => $expiresAt->getTimestamp(),
            ]);
        }

        $this->forgetRequestCache($request);

        return ['unlock_token' => $token, 'expires_at' => $expiresAt->toIso8601String(), 'expires_in' => self::GRANT_TTL_SECONDS];
    }

    /** "Bloquear ahora": drops the grant this request presents. */
    public function revokePresentedGrant(Request $request): void
    {
        $token = $request->header(self::HEADER);
        if (is_string($token) && $token !== '') {
            Cache::forget($this->grantKey($token));
        }

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }

        $this->forgetRequestCache($request);
    }

    /** May the current request see this protected item (= its owner unlocked)? */
    public function reveals(PinProtectable $item, ?Request $request = null): bool
    {
        if (! $item->isPinProtected()) {
            return true;
        }

        $user = $this->unlockedUser($request ?? request());

        return $user !== null && $item->isPinOwnedBy($user);
    }

    /**
     * Moderation exception (the ONLY way staff see protected content): a
     * moment that already carries an open/in-review report stays reviewable
     * by moderators. New reports cannot target it — protected means private,
     * so nobody else can see it to report it.
     */
    public function underModeration(PinProtectable $item): bool
    {
        return $item instanceof LegacyMoment && Report::query()
            ->where('target_type', ReportTargetType::Moment)
            ->where('target_id', $item->getKey())
            ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
            ->exists();
    }

    /** Owner-only write/read on a protected item: 423 CONTENT_LOCKED unless unlocked. */
    public function ensureRevealed(PinProtectable $item, ?Request $request = null): void
    {
        if (! $this->reveals($item, $request)) {
            throw ContentPinException::contentLocked();
        }
    }

    /**
     * The user whose valid grant the current request carries (memoised on
     * the request). Null on console/queue, without a grant, or when the grant
     * does not match the authenticated user / token / PIN epoch.
     */
    public function unlockedUser(Request $request): ?User
    {
        if ($request->attributes->has(self::REQUEST_CACHE)) {
            $cached = $request->attributes->get(self::REQUEST_CACHE);

            return $cached instanceof User ? $cached : null;
        }

        $user = $this->resolveUnlockedUser($request);
        $request->attributes->set(self::REQUEST_CACHE, $user ?? false);

        return $user;
    }

    /** Emails a 6-digit recovery code (hashed in cache, RECOVERY_TTL_MINUTES, 5 tries). */
    public function sendRecoveryCode(User $user): void
    {
        if (blank($user->email)) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->recoveryKey($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::RECOVERY_TTL_MINUTES));

        $this->notify($user, ContentPinNotice::RECOVERY_CODE, $code);
    }

    public function checkRecoveryCode(User $user, string $code): bool
    {
        $key = $this->recoveryKey($user);
        $entry = Cache::get($key);

        if (! is_array($entry) || ($entry['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (Hash::check($code, (string) $entry['hash'])) {
            Cache::forget($key);

            return true;
        }

        $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
        Cache::put($key, $entry, now()->addMinutes(self::RECOVERY_TTL_MINUTES));

        return false;
    }

    private function resolveUnlockedUser(Request $request): ?User
    {
        try {
            $user = $request->user() ?? $request->user('sanctum');
        } catch (Throwable) {
            return null;
        }

        if (! $user instanceof User || $user->content_pin_hash === null) {
            return null;
        }

        $epoch = $this->epoch($user);
        $token = $request->header(self::HEADER);

        if (is_string($token) && $token !== '' && strlen($token) <= 128) {
            $grant = Cache::get($this->grantKey($token));
            $accessToken = $user->currentAccessToken();

            if (is_array($grant)
                && (int) $grant['user_id'] === $user->id
                && hash_equals((string) $grant['epoch'], $epoch)
                && $accessToken instanceof PersonalAccessToken
                && (int) $grant['token_id'] === (int) $accessToken->getKey()) {
                return $user;
            }
        }

        if ($request->hasSession()) {
            $grant = $request->session()->get(self::SESSION_KEY);

            if (is_array($grant)
                && (int) ($grant['user_id'] ?? 0) === $user->id
                && hash_equals((string) ($grant['epoch'] ?? ''), $epoch)
                && (int) ($grant['expires_at'] ?? 0) > now()->getTimestamp()) {
                return $user;
            }
        }

        return null;
    }

    private function store(User $user, string $pin): void
    {
        $user->forceFill([
            'content_pin_hash' => Hash::make($this->digest($pin)),
            'content_pin_set_at' => now(),
            'content_pin_failed_attempts' => 0,
            'content_pin_locked_until' => null,
        ])->save();

        $this->forgetRequestCache();
    }

    private function lockedUntil(User $user): ?CarbonInterface
    {
        $until = $user->content_pin_locked_until;

        return $until !== null && $until->isFuture() ? $until : null;
    }

    /** HMAC with the APP_KEY before hashing ("pepper"). */
    private function digest(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    /** Changes whenever the PIN is (re)set or removed — grants are bound to it. */
    private function epoch(User $user): string
    {
        return hash('sha256', (string) $user->content_pin_hash);
    }

    private function grantKey(string $token): string
    {
        return 'content-pin:grant:'.hash('sha256', $token);
    }

    private function recoveryKey(User $user): string
    {
        return 'content-pin:recovery:'.$user->id;
    }

    private function forgetRequestCache(?Request $request = null): void
    {
        ($request ?? request())->attributes->remove(self::REQUEST_CACHE);
    }

    private function notify(User $user, string $kind, ?string $code = null): void
    {
        if (blank($user->email)) {
            return;
        }

        try {
            $user->notify(new ContentPinNotice($kind, $code));
        } catch (Throwable $e) {
            // Never block the PIN flow on mail delivery; never log the code.
            report($e);
        }
    }
}
