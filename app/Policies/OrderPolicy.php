<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Orders are visible to their owner and to staff with `orders.view` —
 * never by guessing another customer's uuid (no IDOR). Paying is
 * owner-only.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can('orders.view');
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
