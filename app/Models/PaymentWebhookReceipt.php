<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per provider webhook event ever accepted — the (provider,
 * event_id) unique constraint is ProcessPaymentWebhook's idempotency guard
 * (brief §69/§77-§78).
 */
#[Fillable(['provider', 'event_id', 'payload', 'processed_at'])]
class PaymentWebhookReceipt extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
