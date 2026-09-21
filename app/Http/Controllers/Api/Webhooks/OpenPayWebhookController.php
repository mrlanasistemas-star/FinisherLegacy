<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Http\Controllers\Controller;
use App\Services\Commerce\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * `POST /api/webhooks/openpay` — public route (no auth:sanctum, brief
 * §113), same as Stripe's. Openpay's one-time dashboard "verification"
 * handshake (brief §52) carries no `transaction` to process — it's
 * handled here, before OpenPayPaymentGateway::handleWebhook() (which
 * expects a real transaction event) is ever called.
 */
class OpenPayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayRegistry $gateways, ProcessPaymentWebhook $process): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (is_array($payload) && ($payload['type'] ?? null) === 'verification') {
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

            return response()->noContent();
        }

        $outcome = $gateways->get('openpay')->handleWebhook($request);
        $process->handle($outcome);

        return response()->noContent();
    }
}
