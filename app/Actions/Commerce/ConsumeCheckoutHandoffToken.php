<?php

namespace App\Actions\Commerce;

use App\Models\CheckoutHandoffToken;

/**
 * Burns a checkout handoff token (see CreateAppCheckoutSession). A single
 * conditional UPDATE does the check-and-mark, so two concurrent opens of
 * the same URL can never both succeed: the database lets exactly one row
 * update through. Unknown, expired or already used → null.
 */
class ConsumeCheckoutHandoffToken
{
    public function handle(string $token, ?string $ip = null): ?CheckoutHandoffToken
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $hash = CheckoutHandoffToken::hash($token);

        $claimed = CheckoutHandoffToken::query()
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'used_ip' => $ip, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return null;
        }

        return CheckoutHandoffToken::query()->where('token_hash', $hash)->with(['user', 'order'])->first();
    }
}
