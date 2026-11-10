<?php

namespace App\Actions\LegacyTags;

use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\TagAccessSource;
use App\Exceptions\LegacyTags\TagActivationException;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\LegacyTagActivationIntent;
use App\Models\User;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Step 3 of the NFC self-activation (docs/renovacion/manual-activacion-nfc.md):
 * the phone wrote the URL and read it back. We check it is EXACTLY the URL
 * we issued, bind the chip UID (keyed hash) to the unit and hand off to
 * ActivateTag — the only writer of ownership — which is idempotent for its
 * owner. Confirming the same intent twice (double tap, retry after a network
 * error, two concurrent requests) returns the same story.
 *
 * An expired intent can still be confirmed (the "written but not confirmed"
 * recovery case) within config('legacy_tags.activation.intent_recovery_days'),
 * because the written URL — our own, for this unit — is the proof.
 */
class ConfirmTagActivation
{
    public function __construct(
        private readonly ActivateTag $activate,
        private readonly TagLifecycle $lifecycle,
    ) {}

    public function handle(User $user, LegacyTagActivationIntent $intent, string $writtenUrl, ?string $nfcUid = null): LegacyStory
    {
        if ($intent->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(LegacyTagActivationIntent::class, [$intent->uuid]);
        }

        return Cache::lock("legacy-tag-intent:{$intent->id}", 15)->block(10, function () use ($user, $intent, $writtenUrl, $nfcUid) {
            $intent = LegacyTagActivationIntent::query()->findOrFail($intent->id);

            if ($writtenUrl !== $intent->write_url) {
                throw TagActivationException::urlMismatch();
            }

            // Repeat of an already confirmed intent: answer, never re-activate
            // (ownership may have been revoked / transferred since).
            if ($intent->status === LegacyTagActivationIntentStatus::Confirmed) {
                $story = LegacyStory::query()->where('legacy_tag_id', $intent->legacy_tag_id)->first();

                if ($story === null || ! $story->isOwnedBy($user)) {
                    throw TagActivationException::alreadyActivated();
                }

                return $story;
            }

            if ($intent->isExpired()) {
                $recoveryDays = max(0, (int) config('legacy_tags.activation.intent_recovery_days', 30));

                if ($intent->expires_at->lt(now()->subDays($recoveryDays))) {
                    throw TagActivationException::expired();
                }
            }

            $uidHash = StartTagActivation::uidHashFor($nfcUid);

            if ($uidHash !== null && $intent->nfc_uid_hash !== null && ! hash_equals($intent->nfc_uid_hash, $uidHash)) {
                throw TagActivationException::uidMismatch();
            }

            $uidHash ??= $intent->nfc_uid_hash;
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->findOrFail($intent->legacy_tag_id);

            if ($uidHash !== null) {
                StartTagActivation::assertUidFits($tag, $uidHash);
            }

            try {
                $story = $this->activate->handle($tag, $user, TagAccessSource::Nfc);
            } catch (TagAlreadyActivatedException) {
                throw TagActivationException::alreadyActivated();
            } catch (TagNotActivatableException $e) {
                throw TagActivationException::notActivatable($e->reason);
            }

            if ($uidHash !== null) {
                $this->bindUid($tag, $uidHash, $user, $intent);
            }

            $intent->forceFill([
                'status' => LegacyTagActivationIntentStatus::Confirmed,
                'confirmed_at' => $intent->confirmed_at ?? now(),
                'nfc_uid_hash' => $intent->nfc_uid_hash ?? $uidHash,
                'failure_reason' => null,
            ])->save();

            return $story;
        });
    }

    /**
     * The `data` of POST …/activation/{intent}/confirm (docs/api/v1.md §Activación NFC).
     *
     * @return array<string, mixed>
     */
    public function present(LegacyTagActivationIntent $intent, LegacyStory $story): array
    {
        $intent->loadMissing('tag');

        return [
            'status' => 'activated',
            'tag_code' => $intent->tag->public_code,
            'story' => [
                'public_id' => $story->public_id,
                'url' => $story->publicUrl(),
                'edit_url' => route('my-legacy.edit', $story->public_id),
            ],
        ];
    }

    private function bindUid(LegacyTag $tag, string $uidHash, User $user, LegacyTagActivationIntent $intent): void
    {
        DB::transaction(function () use ($tag, $uidHash, $user, $intent) {
            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);

            if ($tag->nfc_uid_hash !== null) {
                if (! hash_equals($tag->nfc_uid_hash, $uidHash)) {
                    throw TagActivationException::uidMismatch();
                }

                return;
            }

            $this->lifecycle->transition($tag, LegacyTagEventType::NfcBound, null, ['nfc_uid_hash' => $uidHash], $user, null, [
                'intent' => $intent->uuid,
                'platform' => $intent->platform->value,
            ]);
        });
    }
}
