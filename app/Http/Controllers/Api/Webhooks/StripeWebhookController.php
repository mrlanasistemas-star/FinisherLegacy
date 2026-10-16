<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Exceptions\UnhandledPaymentWebhookEventException;
use App\Http\Controllers\Controller;
use App\Services\Commerce\PaymentGatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * `POST /api/webhooks/stripe` — public route (no auth:sanctum, brief
 * §113), signature-verified inside StripePaymentGateway::handleWebhook()
 * (bad signature → 400, nothing processed). The webhook is the authority
 * for "paid" (spec §25): the customer's redirect never marks anything.
 *
 * A signed event this app doesn't act on, or one for a PaymentIntent it
 * never created, is acknowledged with 200 so Stripe stops retrying it.
 * Processing is synchronous on purpose (spec §121: never delay payment
 * finalization in a queue when that risks inconsistency) — it is a few
 * row updates.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayRegistry $gateways, ProcessPaymentWebhook $process): Response
    {
        try {
            $outcome = $gateways->get('stripe')->handleWebhook($request);
            $process->handle($outcome);
        } catch (UnhandledPaymentWebhookEventException $e) {
            Log::info('Stripe webhook acknowledged without changes', ['reason' => $e->getMessage()]);

            return response('', 200);
        }

        return response()->noContent();
    }
}
