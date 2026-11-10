<?php

namespace App\Actions\LegacyTags;

use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagActivationPlatform;
use App\Enums\LegacyTagStatus;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Exceptions\LegacyTags\TagActivationException;
use App\Models\LegacyTag;
use App\Models\LegacyTagActivationIntent;
use App\Models\User;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\TagActivationState;
use App\Support\LegacyTags\TagCode;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Step 1 of the NFC self-activation (docs/renovacion/manual-activacion-nfc.md):
 * validate the unit + the person's right to it and hand back the ONE URL the
 * phone may write on the blank chip ({public_base_url}/t/{public_code}).
 * Nothing is activated here — ConfirmTagActivation does that after the
 * phone reads the chip back.
 *
 * Authorization: the secret activation code from the packaging card, or the
 * public tag code when this person is already its owner or its buyer (order).
 * Wrong codes are rate-limited per user+IP and per user per day; a tag code
 * the person has no right to answers exactly like a wrong code (no oracle).
 */
class StartTagActivation
{
    public function __construct(
        private readonly RecordTagScan $scans,
        private readonly StoryPresenter $presenter,
    ) {}

    /**
     * @param  array{activation_code?: ?string, tag_code?: ?string, nfc_uid?: ?string, platform: string}  $input
     */
    public function handle(User $user, array $input, ?string $ip): LegacyTagActivationIntent
    {
        $platform = LegacyTagActivationPlatform::from($input['platform']);
        $uidHash = self::uidHashFor($input['nfc_uid'] ?? null);
        $tag = $this->resolveTag($user, $input, (string) $ip);
        $alreadyMine = $this->assertActivatable($tag, $user);

        if ($uidHash !== null) {
            self::assertUidFits($tag, $uidHash);
        }

        // Lazy housekeeping: this person's stale attempts become `expired`.
        LegacyTagActivationIntent::query()
            ->where('user_id', $user->id)
            ->where('status', LegacyTagActivationIntentStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => LegacyTagActivationIntentStatus::Expired->value, 'updated_at' => now()]);

        $intent = LegacyTagActivationIntent::create([
            'uuid' => (string) Str::uuid(),
            'legacy_tag_id' => $tag->id,
            'user_id' => $user->id,
            'platform' => $platform,
            'nfc_uid_hash' => $uidHash,
            'status' => LegacyTagActivationIntentStatus::Pending,
            'write_url' => $tag->activationWriteUrl(),
            'expires_at' => now()->addMinutes(max(1, (int) config('legacy_tags.activation.intent_ttl_minutes', 15))),
        ]);
        $intent->setRelation('tag', $tag);

        if (! $alreadyMine) {
            $this->scans->handle($tag, TagScanEvent::ActivationStarted, TagAccessSource::Nfc, $user);
        }

        return $intent;
    }

    /**
     * The `data` of POST …/activation/start (docs/api/v1.md §Activación NFC).
     *
     * @return array<string, mixed>
     */
    public function present(LegacyTagActivationIntent $intent, User $user): array
    {
        $tag = $intent->tag;
        $summary = $this->presenter->tag($tag);
        $alreadyMine = TagActivationState::for($tag, $user)->state === TagActivationState::MINE;
        $story = $alreadyMine ? $tag->story : null;

        return [
            'intent_id' => $intent->uuid,
            'tag_code' => $tag->public_code,
            'write_url' => $intent->write_url,
            'expires_at' => $intent->expires_at->toIso8601String(),
            'status' => $alreadyMine ? 'already_activated' : 'ready_to_write',
            'product' => [
                'name' => $summary['product_name'] ?? null,
                'variant' => $summary['variant_name'] ?? null,
                'collection' => $summary['collection']['name'] ?? null,
                'image_url' => $summary['image_url'] ?? null,
            ],
            'story' => $story === null ? null : [
                'public_id' => $story->public_id,
                'url' => $story->publicUrl(),
            ],
        ];
    }

    /**
     * @param  array{activation_code?: ?string, tag_code?: ?string}  $input
     */
    private function resolveTag(User $user, array $input, string $ip): LegacyTag
    {
        $this->ensureNotLockedOut($user, $ip);

        $activationCode = trim((string) ($input['activation_code'] ?? ''));
        $tagCode = trim((string) ($input['tag_code'] ?? ''));
        $normalizedTagCode = $tagCode === '' ? null : TagCode::normalize($tagCode);

        if ($activationCode !== '') {
            $normalized = GenerateActivationCodes::normalize($activationCode);
            $tag = $normalized === null ? null : LegacyTag::query()
                ->where('activation_code_hash', GenerateActivationCodes::hash($normalized))
                ->first();

            // A tag code sent alongside must name the same unit.
            if ($tag === null || ($tagCode !== '' && $tag->public_code !== $normalizedTagCode)) {
                $this->hitWrongCode($user, $ip);

                throw TagActivationException::invalidCode();
            }

            return $tag;
        }

        $tag = $normalizedTagCode === null ? null : LegacyTag::query()->where('public_code', $normalizedTagCode)->first();

        if ($tag === null || ! $this->ownsOrBought($tag, $user)) {
            $this->hitWrongCode($user, $ip);

            throw TagActivationException::codeRequired();
        }

        return $tag;
    }

    /** Owner of an activated tag, or the account that bought it online. */
    private function ownsOrBought(LegacyTag $tag, User $user): bool
    {
        if ($tag->status === LegacyTagStatus::Activated || $tag->status->isMissing()) {
            $athleteId = $user->athlete()->value('id');

            if ($athleteId !== null && $tag->owner_athlete_id === $athleteId) {
                return true;
            }
        }

        if ($tag->order_id === null) {
            return false;
        }

        $tag->loadMissing('order');

        return $tag->order !== null && $tag->order->user_id === $user->id;
    }

    /** @return bool true when the tag is already this person's (idempotent restart / rewrite) */
    private function assertActivatable(LegacyTag $tag, User $user): bool
    {
        if ($tag->status->isMissing()) {
            throw TagActivationException::notActivatable($tag->status->value);
        }

        $state = TagActivationState::for($tag, $user);

        return match ($state->state) {
            TagActivationState::MINE => true,
            TagActivationState::ACTIVATABLE => false,
            TagActivationState::TAKEN => throw TagActivationException::alreadyActivated(),
            default => throw TagActivationException::notActivatable((string) $state->reason),
        };
    }

    /** The chip UID is not bound to another unit, and this unit is not bound to another chip. */
    public static function assertUidFits(LegacyTag $tag, string $uidHash): void
    {
        if ($tag->nfc_uid_hash !== null && ! hash_equals($tag->nfc_uid_hash, $uidHash)) {
            throw TagActivationException::uidMismatch();
        }

        $boundElsewhere = LegacyTag::query()
            ->where('nfc_uid_hash', $uidHash)
            ->whereKeyNot($tag->id)
            ->exists();

        if ($boundElsewhere) {
            throw TagActivationException::uidMismatch();
        }
    }

    private function ensureNotLockedOut(User $user, string $ip): void
    {
        foreach ($this->limits($user, $ip) as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw TagActivationException::tooManyAttempts(RateLimiter::availableIn($key));
            }
        }
    }

    private function hitWrongCode(User $user, string $ip): void
    {
        [$short, $daily] = $this->limits($user, $ip);

        RateLimiter::hit($short[0], max(60, (int) config('legacy_tags.activation.wrong_codes_decay_seconds', 600)));
        RateLimiter::hit($daily[0], 86400);
    }

    /** @return array{0: array{0: string, 1: int}, 1: array{0: string, 1: int}} */
    private function limits(User $user, string $ip): array
    {
        return [
            ['legacy-activation-code:'.$user->id.'|'.$ip, max(1, (int) config('legacy_tags.activation.max_wrong_codes', 5))],
            ['legacy-activation-code-daily:'.$user->id, max(1, (int) config('legacy_tags.activation.max_wrong_codes_per_user_daily', 20))],
        ];
    }

    /** Shared by ConfirmTagActivation for the same UID rules at confirm time. */
    public static function uidHashFor(?string $uid): ?string
    {
        $normalized = GenerateActivationCodes::normalizeUid($uid);

        return $normalized === null ? null : GenerateActivationCodes::nfcUidHash($normalized);
    }
}
