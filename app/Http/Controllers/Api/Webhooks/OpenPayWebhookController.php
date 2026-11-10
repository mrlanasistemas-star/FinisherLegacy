<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Exceptions\UnhandledPaymentWebhookEventException;
use App\Http\Controllers\Controller;
use App\Services\Commerce\PaymentGatewayRegistry;
use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * `POST /api/webhooks/openpay` — public route (no auth:sanctum, brief
 * §113), same as Stripe's.
 *
 * Authentication, two layers:
 *  1. HTTP Basic — OpenPay's only webhook authentication (user/password
 *     typed in the OpenPay dashboard). Enforced whenever credentials are
 *     configured (admin panel or OPENPAY_WEBHOOK_USER/PASSWORD); a wrong
 *     or missing pair gets 401 before anything is read.
 *  2. The body is never trusted: OpenPayPaymentGateway::handleWebhook()
 *     re-fetches the charge by id from OpenPay's API with the private key.
 *
 * Openpay's one-time dashboard "verification" handshake (brief §52)
 * carries no `transaction` to process — it's handled here, before the
 * gateway (which expects a real transaction event) is ever called.
 */
class OpenPayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayRegistry $gateways, ProcessPaymentWebhook $process, PaymentSettings $settings): Response
    {
        if (! $settings->webhookCredentialsMatch($request->getUser(), $request->getPassword())) {
            // Never log what was sent — only that it was rejected.
            Log::warning('Openpay webhook rejected: HTTP Basic credentials do not match');

            return response('', 401, ['WWW-Authenticate' => 'Basic realm="openpay-webhook"']);
        }

        $payload = json_decode($request->getContent(), true);
        $type = is_array($payload) && is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown';

        $settings->recordWebhookReceived($type);

        if ($type === 'verification') {
            // Activating the webhook still requires an admin to paste this
            // code into the Openpay dashboard by hand — Openpay exposes no
            // API to do that step (brief §52). The code itself is a bearer
            // secret for that one-time handshake, so it is never written
            // to logs in full — only that a verification ping arrived.
            Log::info('Openpay webhook verification code received', [
                'code_length' => is_string($payload['verification_code'] ?? null)
                    ? strlen($payload['verification_code'])
                    : null,
            ]);

            // Shown for one hour on /admin/configuracion/pagos (behind
            // `payments.configure`) so the admin can paste it into the
            // OpenPay dashboard — never in logs.
            $code = $payload['verification_code'] ?? null;

            if (is_string($code) && $code !== '' && strlen($code) <= 64) {
                Cache::put(PaymentSettings::VERIFICATION_CACHE_KEY, [
                    'code' => $code,
                    'received_at' => now()->toIso8601String(),
                ], now()->addHour());
            }

            return response()->noContent();
        }

        try {
            $outcome = $gateways->get('openpay')->handleWebhook($request);
            $process->handle($outcome);
        } catch (UnhandledPaymentWebhookEventException $e) {
            Log::info('Openpay webhook acknowledged without changes', ['reason' => $e->getMessage()]);

            return response('', 200);
        }

        return response()->noContent();
    }
}
