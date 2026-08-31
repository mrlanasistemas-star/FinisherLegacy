<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Http\Controllers\Controller;
use App\Services\Commerce\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `POST /api/webhooks/stripe` — public route (no auth:sanctum, brief
 * §113), signature-verified inside the gateway itself
 * (StripePaymentGateway::handleWebhook(), currently a NotConfigured stub
 * — see docs/architecture/commerce.md §Known debt).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayRegistry $gateways, ProcessPaymentWebhook $process): Response
    {
        $outcome = $gateways->get('stripe')->handleWebhook($request);
        $process->handle($outcome);

        return response()->noContent();
    }
}
