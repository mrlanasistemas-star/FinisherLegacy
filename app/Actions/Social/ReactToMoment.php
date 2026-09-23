<?php

namespace App\Actions\Social;

use App\Enums\MomentReactionType;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentReaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Idempotent add/remove (PUT/DELETE) — a double tap or retried request
 * can never create a second reaction or a second notification. The author
 * is notified once per person per Moment (first reaction of any type),
 * never for their own reactions.
 */
class ReactToMoment
{
    public function __construct(private readonly NotifySocialActivity $notify) {}

    public function add(User $user, LegacyMoment $moment, MomentReactionType $type): void
    {
        $hadAnyReaction = LegacyMomentReaction::query()
            ->where('legacy_moment_id', $moment->id)
            ->where('user_id', $user->id)
            ->exists();

        try {
            $reaction = LegacyMomentReaction::query()->firstOrCreate([
                'legacy_moment_id' => $moment->id,
                'user_id' => $user->id,
                'type' => $type,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($reaction->wasRecentlyCreated && ! $hadAnyReaction && $user->id !== $moment->user_id) {
            $this->notify->momentReaction($moment, $user, $type->value);
        }
    }

    public function remove(User $user, LegacyMoment $moment, MomentReactionType $type): void
    {
        LegacyMomentReaction::query()
            ->where('legacy_moment_id', $moment->id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->delete();
    }
}
