<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Actions\Tracking\PairTrackingDevice;
use App\Actions\Tracking\StartTrackingSession;
use App\Enums\TrackingSource;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\TrackingDevice;
use App\Support\Tracking\TrackingSessionPresenter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * gps_band device contract (SPEC §76/§78) — no firmware here, only the
 * server side a future band talks to:
 *
 *  1. `POST tracking/devices/pair {code}` → `{device:{uuid,type,name}, token}`
 *     (token shown once; send it as `X-Tracking-Device-Token`).
 *  2. `POST tracking/devices/session` (device token) → starts (or returns)
 *     the active session of the participation the device is paired to.
 *  3. `POST tracking/sessions/{uuid}/points` (device token) → same ingest
 *     as the phone.
 */
class TrackingDeviceController extends Controller
{
    use ApiResponses;

    public function pair(Request $request, PairTrackingDevice $pair): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $result = $pair->handle($data['code']);

        if ($result === null) {
            throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció.']);
        }

        return $this->respond([
            'device' => [
                'uuid' => $result['device']->uuid,
                'type' => $result['device']->type->value,
                'name' => $result['device']->public_name,
            ],
            'token' => $result['token'],
        ], 'Dispositivo conectado.', status: 201);
    }

    public function startSession(Request $request, StartTrackingSession $start): JsonResponse
    {
        $token = $request->header('X-Tracking-Device-Token');
        $device = is_string($token) && $token !== '' ? TrackingDevice::findByToken($token) : null;

        if ($device === null) {
            throw new AuthenticationException;
        }

        $participant = $device->eventParticipant()->first();
        abort_if($participant === null, 409, 'Este dispositivo no está asignado a ningún evento.');

        $device->update(['last_seen_at' => now()]);
        $result = $start->handle($participant, TrackingSource::GpsBand, $device);

        return $this->respond(
            TrackingSessionPresenter::present($result['session']),
            status: $result['created'] ? 201 : 200,
        );
    }
}
