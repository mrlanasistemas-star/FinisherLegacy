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
            ->greeting(__('Hola'))
            ->line($this->message())
            ->line(__('Las ventas que ya se realizaron conservan la comisión con la que se vendieron.'))
            ->action(__('Ver mi simulador de precios'), route('photographer.photos'))
            ->line(__('Si tienes dudas sobre este cambio, responde a este correo.'));
    }

    private function title(): string
    {
        return __('Cambio en la comisión de Finisher Legacy');
    }

    private function message(): string
    {
        // Long date in the photographer's language ("3 de noviembre de 2026" /
        // "November 3, 2026"): the notification is rendered in their locale.
        $values = [
            'rate' => $this->format($this->commissionPercent),
            'date' => $this->effectiveFrom->copy()->locale(app()->getLocale())->isoFormat('LL'),
        ];
        $personal = $this->scope === 'photographer';
        $changed = $this->previousPercent !== null && abs($this->previousPercent - $this->commissionPercent) > 0.0001;

        if ($changed) {
            $values['previous'] = $this->format((float) $this->previousPercent);
        }

        $rate = match (true) {
            $personal && $changed => __('Tu comisión personalizada será de :rate (antes :previous) a partir del :date.', $values),
            $personal => __('Tu comisión personalizada será de :rate a partir del :date.', $values),
            $changed => __('La comisión de Finisher Legacy será de :rate (antes :previous) a partir del :date.', $values),
            default => __('La comisión de Finisher Legacy será de :rate a partir del :date.', $values),
        };

        $gateway = $this->gatewayFeeAbsorbedBy === 'finisher'
            ? __('La comisión de la pasarela de pago la absorbe Finisher Legacy.')
            : __('La comisión de la pasarela de pago se descuenta de tu ganancia.');

        return $rate.' '.$gateway;
    }

    private function format(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';
    }
}
