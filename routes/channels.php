<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (docs/ARCHITECTURE.md §Realtime)
|--------------------------------------------------------------------------
|
| REST/Inertia stays the source of truth; these channels only push "something
| changed" hints. Every private channel MUST authorize here — never trust the
| client-side channel name.
|
| Public channels need no entry: `live.{token}` (tracking viewers, A8) is
| public on purpose — the 160-bit share token IS the authorization, and its
| payload never carries PII beyond what /live/{token} already shows.
*/

// Per-user: notifications (notification.created), order.updated,
// payment.updated. Only the user themself.
Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

// Admin commerce dashboards (order.updated / payment.updated /
// inventory.updated for staff screens). Permission-gated, not role-gated.
Broadcast::channel('admin.commerce', function (User $user): bool {
    return $user->can('orders.view');
});
