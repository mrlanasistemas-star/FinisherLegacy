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
            ? "Reembolso procesado · Pedido {$this->order->order_number}"
            : "Reembolso parcial procesado · Pedido {$this->order->order_number}";
    }

    protected function viewName(): string
    {
        return 'refunded';
    }

    protected function preheader(): string
    {
        return 'Procesamos tu reembolso de '.$this->formattedAmount().'.';
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
