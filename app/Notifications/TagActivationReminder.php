<?php

namespace App\Notifications;

use App\Mail\Support\OrderSummary;
use App\Models\LegacyTag;
use App\Support\I18n\RecipientLocale;
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

    public function __construct(public readonly LegacyTag $tag)
    {
        // An on-demand address carries no language: use the one of the
        // order (see OrderSummary::locale) or of the owner's account —
        // never the language of the admin who pressed the button.
        $this->locale($tag->order !== null ? OrderSummary::locale($tag->order) : RecipientLocale::of($tag->owner?->user));
    }

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
            ->subject(__('Activa tu Finisher Legacy Tag'))
            ->greeting(__('¡Tu historia te está esperando!'))
            ->line(__('Tu Finisher Legacy Tag todavía no está activado. Actívalo para guardar tu recuerdo: tu carrera, tus fotos y tu dedicatoria.'))
            ->line(__('Código impreso en tu tag: :code', ['code' => $this->tag->displayCode()]))
            ->action(__('Activar mi tag'), $url)
            ->line(__('También puedes acercar tu teléfono al tag (NFC) o entrar a :url y escribir el código.', ['url' => route('activate.index')]));
    }
}
