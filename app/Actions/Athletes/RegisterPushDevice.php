<?php

namespace App\Actions\Athletes;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Backend-only push token registry (product consolidation brief §32) —
 * upserts by (user, token) so a device re-registering after reinstall/
 * re-login updates its existing row instead of accumulating duplicates.
 */
class RegisterPushDevice
{
    public function handle(User $user, string $platform, string $provider, string $token, ?string $deviceName = null): PushDevice
    {
        // `token` is encrypted at rest, so the lookup goes through its
        // plain SHA-256 digest instead — see App\Models\PushDevice.
        $device = PushDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($device !== null) {
            $device->update([
                'platform' => $platform,
                'provider' => $provider,
                'device_name' => $deviceName,
                'last_seen_at' => now(),
                'active' => true,
            ]);

            return $device;
        }

        return PushDevice::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'platform' => $platform,
            'provider' => $provider,
            'token' => $token,
            'device_name' => $deviceName,
            'last_seen_at' => now(),
            'active' => true,
        ]);
    }
}
