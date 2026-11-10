<?php

namespace App\Actions\LegacyTags;

use App\Enums\LegacyTagActivationIntentStatus;
use App\Enums\LegacyTagEventType;
use App\Models\LegacyTag;
use App\Models\LegacyTagActivationIntent;
use App\Models\User;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * The phone could not write / read back the chip (incompatible, locked,
 * moved away, user cancelled…). Recorded on the intent and in the unit's
 * history (legacy_tag_events) so support can see what happened. Never
 * changes ownership; a later confirm of the same intent still works.
 */
class FailTagActivation
{
    public function __construct(private readonly TagLifecycle $lifecycle) {}

    public function handle(User $user, LegacyTagActivationIntent $intent, string $reason): void
    {
        if ($intent->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(LegacyTagActivationIntent::class, [$intent->uuid]);
        }

        $reason = mb_substr(trim($reason), 0, 120);

        DB::transaction(function () use ($user, $intent, $reason) {
            if ($intent->status === LegacyTagActivationIntentStatus::Pending) {
                $intent->forceFill(['status' => LegacyTagActivationIntentStatus::Failed, 'failure_reason' => $reason])->save();
            }

            /** @var LegacyTag $tag */
            $tag = LegacyTag::query()->lockForUpdate()->findOrFail($intent->legacy_tag_id);

            $this->lifecycle->transition($tag, LegacyTagEventType::ActivationFailed, null, [], $user, $reason, [
                'intent' => $intent->uuid,
                'platform' => $intent->platform->value,
            ]);
        });
    }
}
