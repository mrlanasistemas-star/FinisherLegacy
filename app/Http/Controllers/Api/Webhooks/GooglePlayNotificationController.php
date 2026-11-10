<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Actions\Iap\RevokeStorePurchase;
use App\Exceptions\Iap\IapException;
use App\Exceptions\SocialAuthInvalidTokenException;
use App\Http\Controllers\Controller;
use App\Models\StorePurchase;
use App\Services\Auth\IdTokenVerifier;
use App\Services\Iap\GooglePlayVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `POST /api/webhooks/google-play` — Google Play Real-time developer
 * notifications delivered by a Cloud Pub/Sub PUSH subscription
 * (docs/renovacion/compras-dentro-de-app.md).
 *
 * Authentication (one is required; nothing configured → 503):
 *  - Preferred: the push subscription's OIDC token (`Authorization:
 *    Bearer …`), verified against Google's JWKS with our audience
 *    (GOOGLE_PLAY_RTDN_AUDIENCE) and, when set, the push service account
 *    email (GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT).
 *  - Fallback: `?token=` equal to GOOGLE_PLAY_RTDN_TOKEN.
 *
 * voidedPurchaseNotification (refund / chargeback / revoke) and a
 * cancelled one-time purchase → RevokeStorePurchase. Everything else is
 * acknowledged with 200 so Pub/Sub stops redelivering.
 */
class GooglePlayNotificationController extends Controller
{
    private const int ONE_TIME_PRODUCT_CANCELED = 2;

    public function __invoke(Request $request, GooglePlayVerifier $googlePlay, IdTokenVerifier $idTokens, RevokeStorePurchase $revoke): JsonResponse
    {
        $audience = config('iap.google.rtdn.audience');
        $sharedToken = config('iap.google.rtdn.token');

        if (! $googlePlay->configured() || (blank($audience) && blank($sharedToken))) {
            throw IapException::notConfigured('android');
        }

        if (! $this->authenticated($request, $idTokens, $audience, $sharedToken)) {
            Log::warning('Notificación de Google Play rechazada: autenticación de Pub/Sub no válida.');

            return response()->json(['error' => ['code' => 'INVALID_WEBHOOK_SIGNATURE', 'message' => 'No autorizado.']], 401);
        }

        $encoded = $request->input('message.data');
        $notification = is_string($encoded) ? json_decode((string) base64_decode($encoded, true), true) : null;

        if (! is_array($notification) || ($notification['packageName'] ?? null) !== config('iap.google.package_name')) {
            return $this->ok('ignored');
        }

        $voided = $notification['voidedPurchaseNotification'] ?? null;
        $oneTime = $notification['oneTimeProductNotification'] ?? null;

        if (is_array($voided) && is_string($voided['purchaseToken'] ?? null)) {
            return $this->revoke($voided['purchaseToken'], 'voided:'.($voided['refundType'] ?? 'unknown'), $revoke);
        }

        if (is_array($oneTime) && (int) ($oneTime['notificationType'] ?? 0) === self::ONE_TIME_PRODUCT_CANCELED && is_string($oneTime['purchaseToken'] ?? null)) {
            return $this->revoke($oneTime['purchaseToken'], 'cancelled', $revoke);
        }

        return $this->ok('ignored');
    }

    private function revoke(string $purchaseToken, string $reason, RevokeStorePurchase $revoke): JsonResponse
    {
        $purchase = StorePurchase::query()
            ->where('platform', 'android')
            ->where('transaction_key', StorePurchase::androidKey($purchaseToken))
            ->first();

        if ($purchase === null) {
            return $this->ok('ignored');
        }

        $revoke->handle($purchase, $reason);

        return $this->ok('revoked');
    }

    private function authenticated(Request $request, IdTokenVerifier $idTokens, mixed $audience, mixed $sharedToken): bool
    {
        if (filled($audience)) {
            $bearer = $request->bearerToken();

            if (! is_string($bearer) || $bearer === '') {
                return false;
            }

            try {
                $claims = $idTokens->verify($bearer, [
                    'client_ids' => [(string) $audience],
                    'jwks_url' => (string) config('iap.google.rtdn.jwks_url'),
                    'issuers' => array_values((array) config('iap.google.rtdn.issuers')),
                ]);
            } catch (SocialAuthInvalidTokenException) {
                return false;
            }

            $email = config('iap.google.rtdn.service_account_email');

            return blank($email) || (($claims['email'] ?? null) === $email && ($claims['email_verified'] ?? false) === true);
        }

        $given = $request->query('token');

        return is_string($given) && is_string($sharedToken) && hash_equals($sharedToken, $given);
    }

    private function ok(string $result): JsonResponse
    {
        return response()->json(['data' => ['result' => $result]]);
    }
}
