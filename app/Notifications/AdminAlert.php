<?php

namespace App\Notifications;

use App\Enums\AdminAlertCategory;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Realtime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A back-office alert for an admin (docs/renovacion/notificaciones.md §Alertas
 * admin) — only ever sent by App\Actions\Notifications\NotifyAdmins.
 *
 * Channels: `database` always (the admin topbar bell, kept apart from the
 * customer center by its class name), `broadcast` when Reverb is on
 * (`admin-alert.created` on the private user channel), and `mail` when this
 * admin kept email ON for the category. Push goes through PushSender in
 * NotifyAdmins, not here.
 */
class AdminAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly AdminAlertCategory $category,
        public readonly string $event,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        public readonly array $metadata = [],
        public readonly string $level = 'info',
    ) {
        $this->onQueue((string) config('queue.names.realtime', 'default'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = Realtime::enabled() ? ['database', 'broadcast'] : ['database'];

        if ($notifiable instanceof User && filled($notifiable->email) && app(NotificationPreferences::class)->allows($notifiable, $this->category, 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'admin.'.$this->event,
            'category' => $this->category->value,
            'level' => $this->level,
            'action_url' => $this->actionUrl,
            'metadata' => $this->metadata,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->category->label()}] {$this->title}")
            ->greeting($this->title)
            ->line($this->message);

        if ($this->actionUrl !== null) {
            $mail->action('Abrir en el panel', url($this->actionUrl));
        }

        return $mail->line('Puedes elegir qué alertas te llegan por correo en Administración › Alertas › Preferencias.');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'admin.'.$this->event,
            'category' => $this->category->value,
            'level' => $this->level,
            'action_url' => $this->actionUrl,
            'created_at' => now()->toIso8601String(),
        ]))->onQueue((string) config('queue.names.realtime', 'default'));
    }

    public function broadcastType(): string
    {
        return 'admin-alert.created';
    }
}
