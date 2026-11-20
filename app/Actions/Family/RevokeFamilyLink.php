<?php

namespace App\Actions\Family;

use App\Actions\Tracking\EndTrackingShares;
use App\Enums\FamilyLinkStatus;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingShare;
use Illuminate\Support\Facades\DB;

/**
 * Ends a family link — the athlete removing someone, or the contact leaving
 * on their own. Idempotent. Every live share still open for that person is
 * revoked in the same step, so they lose the location immediately (share
 * queries and the realtime fan-out only ever read usable shares).
 */
class RevokeFamilyLink
{
    public function __construct(private readonly EndTrackingShares $end) {}

    public function handle(AthleteFamilyLink $link): AthleteFamilyLink
    {
        if ($link->status === FamilyLinkStatus::Revoked) {
            return $link;
        }

        DB::transaction(function () use ($link) {
            $link->update([
                'status' => FamilyLinkStatus::Revoked,
                'revoked_at' => now(),
                'invite_token_hash' => null,
            ]);

            $this->end->revoke(TrackingShare::query()->where('athlete_family_link_id', $link->id));
        });

        return $link;
    }
}
