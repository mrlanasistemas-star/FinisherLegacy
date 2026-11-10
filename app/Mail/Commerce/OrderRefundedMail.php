<?php

namespace App\Mail\Commerce;

use App\Mail\Support\OrderSummary;
use App\Models\Order;

/**
 * "Reembolso procesado": sent once per refund amount reported by the
 * provider (App\Events\Commerce\OrderRefunded →
 * App\Listeners\Commerce\SendOrderRefundedEmail), full or partial.
 */
class OrderRefundedMail extends OrderMail
{
    public function __construct(Order $order, protected int $amountMinor, protected bool $full)
    {
        parent::__construct($order);
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function isFull(): bool
    {
        return $this->full;
    }

    protected function subjectLine(): string
    {
        return $this->full
            ? __('Reembolso procesado · Pedido :number', ['number' => $this->order->order_number])
            : __('Reembolso parcial procesado · Pedido :number', ['number' => $this->order->order_number]);
    }

    protected function viewName(): string
    {
        return 'refunded';
    }

    protected function preheader(): string
    {
        return __('Procesamos tu reembolso de :amount.', ['amount' => $this->formattedAmount()]);
    }

    protected function extraData(): array
    {
        return [
            'refund' => $this->formattedAmount(),
            'full' => $this->full,
        ];
    }

    private function formattedAmount(): string
    {
        return OrderSummary::money($this->amountMinor, $this->order->currency ?: 'MXN');
    }
}
