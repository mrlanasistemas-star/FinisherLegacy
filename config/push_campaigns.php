<?php

/*
|--------------------------------------------------------------------------
| Campañas push (docs/renovacion/campanas-push.md)
|--------------------------------------------------------------------------
|
| Safety limits of Administración → Notificaciones → Campañas push.
|
| - all_audience_per_hour: how many campaigns to the "Todos" audience may
|   start within any rolling 60-minute window (default 1).
| - batch_size: users per queued batch handed to PushSender.
| - image_max_kb: big-picture upload limit (jpg/png).
| - test_sends_per_hour: "Enviarme una prueba" limit per admin.
|
*/

return [
    'all_audience_per_hour' => (int) env('PUSH_CAMPAIGNS_ALL_PER_HOUR', 1),
    'batch_size' => (int) env('PUSH_CAMPAIGNS_BATCH_SIZE', 500),
    'image_max_kb' => 1024,
    'test_sends_per_hour' => (int) env('PUSH_CAMPAIGNS_TEST_PER_HOUR', 20),
];
