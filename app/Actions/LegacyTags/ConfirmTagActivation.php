<?php

namespace App\Actions\LegacyTags;

use App\Actions\Training\BindCheerCard;
use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\TagAccessSource;
use App\Exceptions\LegacyTags\TagActivationException;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\CheerLink;
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
 *
 * A Tarjeta de ánimo (docs/renovacion/tarjeta-animo.md) goes to
 * BindCheerCard instead of ActivateTag: same ownership rules, no story, and
 * the result is the bound tag.
 */
class ConfirmTagActivation
{
    public function __construct(
        private readonly ActivateTag $activate,
        private readonly TagLifecycle $lifecycle,
        private readonly BindCheerCard $bindCard,
    ) {}

    public function handle(User $user, LegacyTagActivationIntent $intent, string $writtenUrl, ?string $nfcUid = null): LegacyStory|LegacyTag
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
                $confirmedTag = LegacyTag::query()->find($intent->legacy_tag_id);

                if ($confirmedTag !== null && $confirmedTag->isCheerCard()) {
                    if ($confirmedTag->owner_athlete_id === null || $confirmedTag->owner_athlete_id !== $user->athlete()->value('id')) {
                        throw TagActivationException::alreadyActivated();
                    }

                    return $confirmedTag;
                }

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
                $story = $tag->isCheerCard()
                    ? $this->bindCard->bind($tag, $this->cheerLink($intent, $user), $user, 'app')
                    : $this->activate->handle($tag, $user, TagAccessSource::Nfc);
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
    public function present(LegacyTagActivationIntent $intent, LegacyStory|LegacyTag $story): array
    {
        $intent->loadMissing('tag');

        if ($story instanceof LegacyTag) {
            $story->loadMissing('cheerLink');

            return [
                'status' => 'activated',
                'purpose' => 'cheer',
                'tag_code' => $intent->tag->public_code,
                'story' => null,
                'cheer_link' => $story->cheerLink === null ? null : [
                    'uuid' => $story->cheerLink->uuid,
                    'title' => $story->cheerLink->title,
                    'url' => $story->cheerLink->url(),
                ],
            ];
        }

        return [
            'status' => 'activated',
            'purpose' => 'story',
            'tag_code' => $intent->tag->public_code,
            'story' => [
                'public_id' => $story->public_id,
                'url' => $story->publicUrl(),
                'edit_url' => route('my-legacy.edit', $story->public_id),
            ],
        ];
    }

    /** The link chosen at start, still this person's. */
    private function cheerLink(LegacyTagActivationIntent $intent, User $user): CheerLink
    {
        $link = $intent->cheer_link_id !== null
            ? CheerLink::query()->whereKey($intent->cheer_link_id)->where('user_id', $user->id)->first()
            : null;

        return $link ?? throw TagActivationException::notActivatable('cheer_card');
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

            // Re-checked under the row lock: another tag may have bound this
            // same chip between start and confirm. The unique index on
            // legacy_tags.nfc_uid_hash is the final guard for true races.
            StartTagActivation::assertUidFits($tag, $uidHash);

            $this->lifecycle->transition($tag, LegacyTagEventType::NfcBound, null, ['nfc_uid_hash' => $uidHash], $user, null, [
                'intent' => $intent->uuid,
                'platform' => $intent->platform->value,
            ]);
        });
    }
}
