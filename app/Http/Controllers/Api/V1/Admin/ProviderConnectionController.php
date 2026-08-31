<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Integrations\TestProviderConnection;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ProviderConnection;
use Illuminate\Http\JsonResponse;

/**
 * Same `TestProviderConnection` Action the web admin uses (brief §30/§50/
 * §122) — never persists/returns a secret, only pass/fail + timestamp.
 */
class ProviderConnectionController extends Controller
{
    use ApiResponses;

    public function test(ProviderConnection $providerConnection, TestProviderConnection $action): JsonResponse
    {
        $result = $action->handle($providerConnection);

        return $this->respond([
            'success' => $result->success,
            'latency_ms' => $result->latencyMs,
            'message' => $result->message,
        ]);
    }
}
