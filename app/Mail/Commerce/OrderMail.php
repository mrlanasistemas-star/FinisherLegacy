<?php

namespace App\Mail\Commerce;

use App\Mail\Support\OrderSummary;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for every customer order email: queued, only after the surrounding
 * DB transaction commits, rendered with the branded layout
 * (resources/views/emails/layouts/brand) plus a plain-text alternative.
 * The order data is built when the worker renders (fresh from the DB), via
 * App\Mail\Support\OrderSummary. Sending once is the listener's job
 * (App\Mail\Support\OrderEmailLedger), not the Mailable's.
 */
abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(protected Order $order)
    {
        $this->afterCommit();
    }

    /** The Order this email is about (kept off the view data, which gets the summary). */
    public function order(): Order
    {
        return $this->order;
    }

    abstract protected function subjectLine(): string;

    /** Base view name under emails/orders (the "-text" twin is the plain-text part). */
    abstract protected function viewName(): string;

    /** Inbox preview text. */
    abstract protected function preheader(): string;

    /** @return array<string, mixed> */
    protected function extraData(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
            tags: ['order', $this->viewName()],
            metadata: ['order_uuid' => (string) $this->order->uuid],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.'.$this->viewName(),
            text: 'emails.orders.'.$this->viewName().'-text',
            with: [
                'title' => $this->subjectLine(),
                'preheader' => $this->preheader(),
                'order' => OrderSummary::for($this->order),
                ...$this->extraData(),
            ],
        );
    }
}
