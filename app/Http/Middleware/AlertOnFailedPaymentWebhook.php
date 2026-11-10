<?php

namespace App\Http\Middleware;

use App\Actions\Notifications\NotifyAdmins;
use App\Enums\AdminAlertCategory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps the payment webhook routes (routes/api.php): when a delivery ends in
 * 4xx/5xx — wrong HTTP Basic credentials, an amount mismatch, an exception —
 * admins with `payments.view` get one Pagos alert, at most every 30 minutes
 * per provider and status. Never changes the response the provider gets.
 */
class AlertOnFailedPaymentWebhook
{
    public function __construct(private readonly NotifyAdmins $notify) {}

    public function handle(Request $request, Closure $next, string $provider = 'openpay'): Response
    {
        $response = $next($request);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $label = $provider === 'openpay' ? 'OpenPay' : ucfirst($provider);

            $this->notify->handle(
                AdminAlertCategory::Pagos,
                'webhook_failed',
                "Webhook de {$label} con error",
                $status === 401
                    ? "{$label} intentó notificar un pago con usuario/contraseña del webhook incorrectos (401). Revisa la configuración."
                    : "Una notificación de {$label} respondió {$status}. Los pagos podrían no confirmarse solos; revisa los registros.",
                Route::has('admin.settings.payments') ? route('admin.settings.payments', absolute: false) : '/admin',
                ['provider' => $provider, 'status' => $status],
                throttleKey: "webhook:{$provider}:{$status}",
                throttleMinutes: 30,
                level: 'danger',
            );
        }

        return $response;
    }
}
