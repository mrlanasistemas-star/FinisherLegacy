<?php

namespace App\Observers;

use App\Events\Realtime\OrderUpdated;
use App\Events\Realtime\PaymentUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Realtime;

/**
 * Turns Order/Payment state transitions into realtime hints without touching
 * any commerce Action (they all go through Eloquent `update()`), registered
 * in AppServiceProvider. The events implement ShouldDispatchAfterCommit, so a
 * rolled-back transition never broadcasts.
 */
class CommerceBroadcastObserver
{
    private const array ORDER_FIELDS = ['status', 'payment_status', 'fulfillment_status'];

    public function updated(Order|Payment $model): void
    {
        if (! Realtime::enabled()) {
            return;
        }

        if ($model instanceof Order) {
            if ($model->wasChanged(self::ORDER_FIELDS)) {
                OrderUpdated::dispatch($model);
            }

            return;
        }

        if ($model->wasChanged('status')) {
            PaymentUpdated::dispatch($model);
        }
    }

    public function created(Order|Payment $model): void
    {
        if (Realtime::enabled() && $model instanceof Payment) {
            PaymentUpdated::dispatch($model);
        }
    }
}
