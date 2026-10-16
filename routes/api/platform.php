<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Platform (A1) — realtime auth for the mobile app.
|--------------------------------------------------------------------------
| `POST /api/v1/broadcasting/auth` authorizes private channels
| (routes/channels.php) with the Sanctum bearer token, same contract as the
| web `/broadcasting/auth` (session). Reverb/Pusher protocol.
*/
Broadcast::routes([
    'prefix' => 'v1',
    'middleware' => ['auth:sanctum', 'user.token', 'throttle:api'],
]);
