<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A workout action that doesn't fit the workout's current state: a second
 * live workout while one is running, finishing a discarded one, discarding a
 * completed one, sharing twice… Renders as 409 CONFLICT with
 * `details.reason` (and `details.workout_uuid` when there is a workout the
 * app should open instead).
 */
class WorkoutStateException extends ApiException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message, string $reason = 'workout_state', array $details = [])
    {
        parent::__construct($message, ApiErrorCode::Conflict, 409, ['reason' => $reason, ...$details]);
    }
}
