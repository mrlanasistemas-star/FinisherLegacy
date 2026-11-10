<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Iap\RevokeStorePurchase;
use App\Exceptions\Iap\IapException;
use App\Http\Controllers\Controller;
use App\Models\StorePurchase;
use App\Services\Iap\AppleJwsVerifier;
use App\Services\Iap\AppStoreVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * `POST /api/webhooks/app-store` — App Store Server Notifications V2
 * (docs/renovacion/compras-dentro-de-app.md). Public route: Apple signs
 * every notification, so the `signedPayload` JWS AND the nested
 * `signedTransactionInfo` are verified against Apple Root CA - G3 before
 * anything is read; a forged body gets 400.
 *
 * REFUND / REVOKE → RevokeStorePurchase (capacity removed, photos kept).
 * REFUND_REVERSED → reinstated. Everything else (TEST, CONSUMPTION_REQUEST,
 * subscriptions…) is acknowledged with 200 so Apple stops retrying.
 */
class AppStoreNotificationController extends Controller
{
    private const array REVOKING_TYPES = ['REFUND', 'REVOKE'];

    public function __invoke(Request $request, AppleJwsVerifier $verifier, AppStoreVerifier $appStore, RevokeStorePurchase $revoke): JsonResponse
    {
        if (! $appStore->configured()) {
            throw IapException::notConfigured('ios');
        }

        $signedPayload = $request->input('signedPayload');

        if (! is_string($signedPayload) || $signedPayload === '') {
            return response()->json(['error' => ['code' => 'INVALID_WEBHOOK_SIGNATURE', 'message' => 'Falta signedPayload.']], 400);
        }

        try {
            $notification = $verifier->verify($signedPayload);
            $data = (array) ($notification['data'] ?? []);
            $transaction = is_string($data['signedTransactionInfo'] ?? null) ? $verifier->verify($data['signedTransactionInfo']) : null;
        } catch (RuntimeException $e) {
            Log::warning('Notificación de App Store rechazada: firma no válida.', ['error' => $e->getMessage()]);

            return response()->json(['error' => ['code' => 'INVALID_WEBHOOK_SIGNATURE', 'message' => 'Firma no válida.']], 400);
        }

        $type = (string) ($notification['notificationType'] ?? '');

        if (($data['bundleId'] ?? null) !== config('iap.apple.bundle_id')) {
            return $this->ok('ignored');
        }

        $appAppleId = config('iap.apple.app_apple_id');
        if (($data['environment'] ?? null) === 'Production' && filled($appAppleId) && (string) ($data['appAppleId'] ?? '') !== (string) $appAppleId) {
            return $this->ok('ignored');
        }

        $transactionId = is_array($transaction) ? (string) ($transaction['transactionId'] ?? '') : '';
        $purchase = $transactionId === '' ? null : StorePurchase::query()
            ->where('platform', 'ios')
            ->where('transaction_key', $transactionId)
            ->first();

        if ($purchase === null) {
            if (in_array($type, self::REVOKING_TYPES, true)) {
                Log::info('Reembolso de App Store para una transacción que no otorgó espacio.', ['type' => $type]);
            }

            return $this->ok('ignored');
        }

        if (in_array($type, self::REVOKING_TYPES, true)) {
            $revoke->handle($purchase, strtolower($type).':'.($transaction['revocationReason'] ?? 'unknown'));

            return $this->ok('revoked');
        }

        if ($type === 'REFUND_REVERSED') {
            $revoke->reinstate($purchase);

            return $this->ok('reinstated');
        }

        return $this->ok('ignored');
    }

    private function ok(string $result): JsonResponse
    {
        return response()->json(['data' => ['result' => $result]]);
    }
}
