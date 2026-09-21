<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\RegisterPushDevice;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `POST/DELETE /api/v1/me/push-devices` (product consolidation brief §32)
 * — stores a push token for a future mobile client. Never calls
 * Expo/FCM/APNs itself; which provider actually sends anything is decided
 * in the Mobile repo.
 */
class PushDeviceController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function store(Request $request, RegisterPushDevice $register): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
            'provider' => ['required', 'string', 'max:50'],
            'token' => ['required', 'string', 'max:500'],
            'device_name' => ['nullable', 'string', 'max:150'],
        ]);

        $device = $register->handle(
            $this->sanctumUser($request),
            $data['platform'],
            $data['provider'],
            $data['token'],
            $data['device_name'] ?? null,
        );

        return $this->respond([
            'uuid' => $device->uuid,
            'platform' => $device->platform,
            'provider' => $device->provider,
        ], 'Dispositivo registrado.', status: 201);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $device = PushDevice::query()
            ->where('user_id', $this->sanctumUser($request)->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $device->delete();

        return $this->respond(null, 'Dispositivo eliminado.');
    }
}
