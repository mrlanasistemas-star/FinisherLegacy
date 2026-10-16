<?php

namespace App\Http\Requests\Api;

use App\Models\TrackingDevice;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST tracking/sessions/{session:uuid}/points`.
 *
 * Authorization (runs before validation, so strangers never learn the
 * payload rules): either the session owner's Sanctum user token, or the
 * session's paired device sending `X-Tracking-Device-Token` (gps_band
 * contract). The route is outside `auth:sanctum` so both work.
 *
 * Shape/range only — the domain rules (time window, duplicates, distance
 * filtering) live in App\Actions\Tracking\IngestTrackingPoints.
 */
class IngestTrackingPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TrackingSession $session */
        $session = $this->route('session');
        $deviceToken = $this->header('X-Tracking-Device-Token');

        if (is_string($deviceToken) && $deviceToken !== '') {
            $device = TrackingDevice::findByToken($deviceToken);

            return $device !== null && $session->tracking_device_id === $device->id;
        }

        $user = $this->user('sanctum');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return Gate::forUser($user)->allows('ingest', $session);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $max = (int) config('tracking.batch_max', 100);

        return [
            'points' => ['required', 'array', 'min:1', "max:{$max}"],
            'points.*' => ['required', 'array'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'points.*.altitude' => ['nullable', 'numeric', 'between:-500,9000'],
            'points.*.speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'points.*.battery' => ['nullable', 'integer', 'between:0,100'],
            'points.*.recorded_at' => ['required', 'date'],
        ];
    }
}
