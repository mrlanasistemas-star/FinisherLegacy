<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content-PIN security mail (App\Services\Privacy\ContentPin): the 6-digit
 * recovery code, or the notice that the PIN was reset. Mail only, sent
 * synchronously (the code must arrive now, even without a queue worker) and
 * never stored in the notifications table — the code must not live anywhere
 * but in the inbox and as a hash in the cache.
 */
class ContentPinNotice extends Notification
{
    public const RECOVERY_CODE = 'recovery_code';

    public const RESET = 'reset';

    public function __construct(
        public readonly string $kind,
        #[\SensitiveParameter] private readonly ?string $code = null,
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
        if ($this->kind === self::RECOVERY_CODE) {
            return (new MailMessage)
                ->subject(__('Tu código para restablecer el NIP'))
                ->greeting(__('Restablecer NIP'))
                ->line(__('Usa este código en la app para crear un NIP nuevo:'))
                ->line('**'.$this->code.'**')
                ->line(__('Vence en :minutes minutos. Si no lo pediste, ignora este correo: tu contenido sigue protegido.', ['minutes' => 15]));
        }

        return (new MailMessage)
            ->subject(__('Tu NIP de Finisher Legacy cambió'))
            ->greeting(__('Se restableció tu NIP'))
            ->line(__('Acabas de restablecer el NIP que protege tus recuerdos y fotos privados.'))
            ->line(__('Si no fuiste tú, cambia tu contraseña de inmediato y restablece el NIP otra vez.'));
    }
}
