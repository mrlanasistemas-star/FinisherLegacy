<?php

namespace App\Actions\Notifications;

use App\Enums\AdminAlertCategory;
use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Support\Facades\Route;

/**
 * Daily Pagos check (scheduled in routes/console.php): when OpenPay is the
 * active gateway but its credentials are incomplete, admins get one alert a
 * day listing what's missing — checkout cannot charge cards meanwhile.
 * Reads PaymentSettings::adminSummary() (never a secret).
 */
class AlertIfPaymentGatewayUnconfigured
{
    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly NotifyAdmins $notify,
    ) {}

    public function handle(): bool
    {
        $summary = $this->settings->adminSummary();

        if (($summary['default_gateway'] ?? 'openpay') !== 'openpay' || ($summary['openpay']['configured'] ?? false)) {
            return false;
        }

        $missing = implode(', ', (array) ($summary['openpay']['missing'] ?? []));

        $this->notify->handle(
            AdminAlertCategory::Pagos,
            'gateway_not_configured',
            'OpenPay sin configurar',
            'La tienda no puede cobrar con tarjeta: falta '.($missing !== '' ? $missing : 'completar la configuración').'.',
            Route::has('admin.settings.payments') ? route('admin.settings.payments', absolute: false) : '/admin',
            ['missing' => $summary['openpay']['missing'] ?? []],
            throttleKey: 'gateway:openpay',
            throttleMinutes: 20 * 60,
            level: 'danger',
        );

        return true;
    }
}
