<?php

namespace App\Services\Commerce\Payments;

use Openpay\Data\Openpay;

/**
 * The one place that builds the official `openpay/sdk` client. Kept as
 * its own (container-resolved) class so tests can swap it for a fake and
 * never touch the network.
 */
class OpenPayClientFactory
{
    /**
     * Untyped return on purpose — Openpay\Data\OpenpayApiResourceBase
     * exposes `charges`/`customers`/etc. only through `__get`.
     */
    public function make(string $merchantId, string $privateKey, string $country, bool $productionMode): mixed
    {
        // The SDK's getSandboxMode() lets a process-level
        // OPENPAY_PRODUCTION_MODE env var override setProductionMode().
        // Pin it to the value we resolved (panel > .env) so both agree.
        putenv('OPENPAY_PRODUCTION_MODE='.($productionMode ? 'true' : 'false'));

        $client = Openpay::getInstance($merchantId, $privateKey, $country, request()->ip());

        Openpay::setProductionMode($productionMode);

        return $client;
    }
}
