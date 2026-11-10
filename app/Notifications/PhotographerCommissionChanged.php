<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a photographer that the commission Finisher Legacy keeps on their
 * photo sales changes (global rate, their personal override, or who
 * absorbs the payment gateway fee), and from which date. Database (the
 * notification inbox, same {title, message, type, action_url} shape as
 * App\Notifications\AthleteAlert) + mail. Queued; sales already made keep
 * their original split.
 */
class PhotographerCommissionChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public const string TYPE = 'photographer_commission_changed';

    public function __construct(
        public readonly float $commissionPercent,
        public readonly ?float $previousPercent,
        public readonly CarbonInterface $effectiveFrom,
        public readonly string $scope,
        public readonly string $gatewayFeeAbsorbedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type' => self::TYPE,
            'action_url' => route('photographer.photos'),
            'metadata' => [
                'commission_percent' => $this->commissionPercent,
                'previous_percent' => $this->previousPercent,
                'effective_from' => $this->effectiveFrom->toIso8601String(),
                'scope' => $this->scope,
                'gateway_fee_absorbed_by' => $this->gatewayFeeAbsorbedBy,
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Hola')
            ->line($this->message())
            ->line('Las ventas que ya se realizaron conservan la comisión con la que se vendieron.')
            ->action('Ver mi simulador de precios', route('photographer.photos'))
            ->line('Si tienes dudas sobre este cambio, responde a este correo.');
    }

    private function title(): string
    {
        return 'Cambio en la comisión de Finisher Legacy';
    }

    private function message(): string
    {
        $date = $this->effectiveFrom->copy()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $rate = $this->format($this->commissionPercent);
        $who = $this->scope === 'photographer' ? 'Tu comisión personalizada' : 'La comisión de Finisher Legacy';
        $from = $this->previousPercent !== null && abs($this->previousPercent - $this->commissionPercent) > 0.0001
            ? ' (antes '.$this->format($this->previousPercent).')'
            : '';
        $gateway = $this->gatewayFeeAbsorbedBy === 'finisher'
            ? ' La comisión de la pasarela de pago la absorbe Finisher Legacy.'
            : ' La comisión de la pasarela de pago se descuenta de tu ganancia.';

        return "{$who} será de {$rate}{$from} a partir del {$date}.{$gateway}";
    }

    private function format(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';
    }
}
