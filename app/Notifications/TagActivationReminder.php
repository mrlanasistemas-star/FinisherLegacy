<?php

namespace App\Notifications;

use App\Models\LegacyTag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Reenviar instrucciones" (Legacy → Activaciones): a short mail to the
 * buyer of a sold/delivered tag that was never activated. Sent on demand
 * route (Notification::route('mail', …)) — the buyer may have no account.
 * Queued: if mail is not configured the job simply waits/fails in the
 * queue; nothing in the admin flow depends on delivery.
 */
class TagActivationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LegacyTag $tag) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('activate.show', $this->tag->public_code);

        return (new MailMessage)
            ->subject('Activa tu Finisher Legacy Tag')
            ->greeting('¡Tu historia te está esperando!')
            ->line('Tu Finisher Legacy Tag todavía no está activado. Actívalo para guardar tu recuerdo: tu carrera, tus fotos y tu dedicatoria.')
            ->line('Código impreso en tu tag: '.$this->tag->displayCode())
            ->action('Activar mi tag', $url)
            ->line('También puedes acercar tu teléfono al tag (NFC) o entrar a '.route('activate.index').' y escribir el código.');
    }
}
