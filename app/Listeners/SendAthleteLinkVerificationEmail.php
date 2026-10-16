<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Throwable;

/**
 * User does not implement MustVerifyEmail (verification is not a gate to
 * use the app), so Laravel's own SendEmailVerificationNotification skips
 * it. Verifying is still what unlocks linking an imported race history to
 * the account (App\Listeners\LinkVerifiedUserToAthlete), so every new
 * unverified registration gets the verification email anyway. A mail
 * outage must never fail the registration itself.
 */
class SendAthleteLinkVerificationEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
