<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail half of a prepaid-plan reminder (30 / 7 / 1 days before the
 * term of a story expansion ends, and when it ends) — the in-app + push
 * half goes through SendAthleteNotification
 * (App\Console\Commands\RemindExpiringPlans). Queued. It never mentions
 * how the payment is processed, and always says that nothing is deleted.
 */
class PlanExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $headline,
        public readonly string $body,
        public readonly string $actionUrl,
        public readonly bool $expired,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting($this->headline)
            ->line($this->body)
            ->action($this->expired ? 'Renovar mi plan' : 'Ver mi recuerdo', $this->actionUrl)
            ->line('Pase lo que pase, tu contenido no se borra: todo lo que ya subiste sigue visible y lo puedes descargar cuando quieras.');
    }
}
