<?php

namespace App\Actions\Account;

use App\Actions\Social\DeleteMoment;
use App\Enums\MedalVisibility;
use App\Enums\UserStatus;
use App\Models\Athlete;
use App\Models\AthleteFollow;
use App\Models\LegacyMomentComment;
use App\Models\LegacyMomentReaction;
use App\Models\Medal;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Self-service account deletion (App Store / Play requirement).
 *
 * What goes: login ability (all tokens), personal data on the User
 * (name/email/phone anonymized), the public profile and its photos, all
 * social content (moments, comments, reactions, follows, blocks), push
 * devices and social sign-in links.
 *
 * What stays, de-identified: orders/payments (accounting obligations),
 * official event participations/results (they belong to the event's
 * records, and the canonical Athlete is simply unlinked from this User),
 * and personal medals, which are forced private so nothing remains
 * publicly visible. The User row is soft-deleted, so foreign keys from
 * those records stay valid. See docs/ARCHITECTURE.md §Eliminación de cuenta.
 */
class DeleteAccount
{
    public function __construct(private readonly DeleteMoment $deleteMoment) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->pushDevices()->delete();
            $user->socialAccounts()->delete();

            foreach ($user->moments()->get() as $moment) {
                $this->deleteMoment->handle($moment);
            }

            LegacyMomentComment::query()->where('user_id', $user->id)->delete();
            LegacyMomentReaction::query()->where('user_id', $user->id)->delete();
            AthleteFollow::query()->where('follower_id', $user->id)->orWhere('following_id', $user->id)->delete();
            UserBlock::query()->where('blocker_id', $user->id)->orWhere('blocked_id', $user->id)->delete();
            Report::query()->where('reporter_id', $user->id)->update(['reporter_id' => null]);

            if ($profile = $user->athleteProfile) {
                foreach (['profile_photo_path', 'cover_photo_path'] as $column) {
                    if ($profile->{$column}) {
                        Storage::disk('public')->delete($profile->{$column});
                    }
                }
                $profile->delete();
            }

            Medal::query()->where('user_id', $user->id)->update(['visibility' => MedalVisibility::Private]);
            Athlete::query()->where('user_id', $user->id)->update(['user_id' => null]);

            $user->forceFill([
                'first_name' => 'Usuario',
                'last_name' => 'eliminado',
                'email' => "deleted-{$user->id}-".Str::lower(Str::random(8)).'@deleted.finisherlegacy.invalid',
                'phone' => null,
                'avatar_path' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'status' => UserStatus::Blocked,
            ])->save();

            $user->delete();
        });
    }
}
