<?php

namespace App\Services\Commerce\Payments;

use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Openpay\Data\OpenpayApiError;
use Throwable;

/**
 * Admin-editable payment configuration (/admin/configuracion/pagos).
 *
 * Values saved in `payment_settings` override config/finisher.php (.env)
 * key by key; anything left empty keeps the .env value. The merge is
 * applied to the runtime config once per request (see
 * App\Providers\PaymentSettingsServiceProvider) so every existing reader
 * of `config('finisher.payments.*')` — gateways, checkout, photo fees —
 * sees the same values without knowing this class exists.
 *
 * The cache holds the row's RAW attributes, i.e. the private key still
 * encrypted — a decrypted secret never lands in the cache store.
 */
class PaymentSettings
{
    private const string CACHE_KEY = 'payment_settings.row.v1';

    /** Last OpenPay webhook verification code (one hour, admin-only). */
    public const string VERIFICATION_CACHE_KEY = 'payment_settings.openpay_webhook_verification';

    /** @var list<string> */
    public const array GATEWAYS = ['openpay', 'stripe'];

    /** @var list<string> */
    public const array OPENPAY_COUNTRIES = ['MX'];

    public function __construct(private readonly OpenPayClientFactory $openpay) {}

    public function row(): ?PaymentSetting
    {
        try {
            /** @var array<string, mixed> $attributes */
            $attributes = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => PaymentSetting::query()->first()?->getAttributes() ?? [],
            );
        } catch (Throwable) {
            // Table not migrated yet (fresh install, early boot) — .env only.
            return null;
        }

        if ($attributes === []) {
            return null;
        }

        $row = (new PaymentSetting)->setRawAttributes($attributes, true);
        $row->exists = true;

        return $row;
    }

    /**
     * Overrides `finisher.payments.*` with whatever an admin saved.
     */
    public function applyToConfig(): void
    {
        $row = $this->row();

        if ($row === null) {
            return;
        }

        $overrides = array_filter([
            'finisher.payments.default_gateway' => in_array($row->default_gateway, self::GATEWAYS, true) ? $row->default_gateway : null,
            'finisher.payments.openpay.merchant_id' => $row->openpay_merchant_id,
            'finisher.payments.openpay.public_key' => $row->openpay_public_key,
            'finisher.payments.openpay.private_key' => $this->privateKey($row),
            'finisher.payments.openpay.country' => $row->openpay_country,
            'finisher.payments.openpay.production_mode' => $row->openpay_production_mode,
        ], fn ($value) => $value !== null && $value !== '');

        if ($row->openpay_use_3d_secure) {
            $overrides['finisher.payments.openpay.use_3d_secure'] = true;
        }

        config($overrides);
    }

    /**
     * @param  array<string, mixed>  $data  Validated by UpdatePaymentSettingsRequest.
     */
    public function save(array $data, User $by): PaymentSetting
    {
        $row = PaymentSetting::query()->first() ?? new PaymentSetting;
        $before = $row->exists ? $this->auditSnapshot($row) : [];

        $row->fill([
            'default_gateway' => $data['default_gateway'],
            'openpay_merchant_id' => $this->clean($data['openpay_merchant_id'] ?? null),
            'openpay_public_key' => $this->clean($data['openpay_public_key'] ?? null),
            'openpay_country' => $data['openpay_country'] ?? 'MX',
            'openpay_production_mode' => (bool) ($data['openpay_production_mode'] ?? false),
            'openpay_use_3d_secure' => (bool) ($data['openpay_use_3d_secure'] ?? false),
            'updated_by' => $by->id,
        ]);

        // A blank private key means "keep the one already saved".
        $privateKey = $this->clean($data['openpay_private_key'] ?? null);

        if ($privateKey !== null) {
            $row->openpay_private_key = $privateKey;
            $row->openpay_private_key_last4 = substr($privateKey, -4);
        }

        if ($row->isDirty(['openpay_merchant_id', 'openpay_private_key', 'openpay_production_mode', 'openpay_country'])) {
            $row->openpay_verified_at = null;
        }

        $row->save();

        $this->forget();
        $this->applyToConfig();

        activity('payments')
            ->causedBy($by)
            ->performedOn($row)
            ->event('updated')
            ->withProperties(['old' => $before, 'attributes' => $this->auditSnapshot($row)])
            ->log('Actualizó la configuración de pagos');

        return $row;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Status for the admin page — only public / non-secret values ever.
     *
     * @return array<string, mixed>
     */
    public function adminSummary(): array
    {
        $row = $this->row();
        $config = (array) config('finisher.payments');
        $openpay = (array) ($config['openpay'] ?? []);
        $stripe = (array) ($config['stripe'] ?? []);

        $privateFromPanel = $row !== null
            && filled($row->openpay_private_key_last4)
            && filled($row->getRawOriginal('openpay_private_key'));
        $privateKey = (string) ($openpay['private_key'] ?? '');
        $privateLast4 = $privateFromPanel
            ? $row->openpay_private_key_last4
            : ($privateKey !== '' ? substr($privateKey, -4) : null);

        $missing = [];

        if (blank($openpay['merchant_id'] ?? null)) {
            $missing[] = 'Merchant ID';
        }

        if (blank($openpay['public_key'] ?? null)) {
            $missing[] = 'Llave pública';
        }

        if ($privateKey === '') {
            $missing[] = 'Llave privada';
        }

        $production = (bool) ($openpay['production_mode'] ?? false);

        return [
            'default_gateway' => (string) ($config['default_gateway'] ?? 'openpay'),
            'api_gateway' => (string) ($config['api_gateway'] ?? 'stripe'),
            'openpay' => [
                'merchant_id' => $openpay['merchant_id'] ?? null,
                'public_key' => $openpay['public_key'] ?? null,
                'private_key_hint' => $privateLast4 !== null ? '••••'.$privateLast4 : null,
                'private_key_source' => $privateLast4 === null ? null : ($privateFromPanel ? 'panel' : 'env'),
                'country' => (string) ($openpay['country'] ?? 'MX'),
                'production_mode' => $production,
                'use_3d_secure' => (bool) ($openpay['use_3d_secure'] ?? false),
                'configured' => $missing === [],
                'missing' => $missing,
                'verified_at' => $row?->openpay_verified_at?->toIso8601String(),
                'webhook_url' => route('api.webhooks.openpay'),
                'webhook_verification' => Cache::get(self::VERIFICATION_CACHE_KEY),
                'dashboard_url' => $production ? 'https://dashboard.openpay.mx' : 'https://sandbox-dashboard.openpay.mx',
            ],
            'stripe' => [
                'configured' => filled($stripe['key'] ?? null) && filled($stripe['secret'] ?? null),
                'webhook_configured' => filled($stripe['webhook_secret'] ?? null),
                'webhook_url' => route('api.webhooks.stripe'),
            ],
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calls OpenPay's read-only "merchant info" endpoint with the current
     * (merged) credentials. Never creates a charge.
     *
     * @return array{ok: bool, message: string}
     */
    public function testOpenPayConnection(): array
    {
        $openpay = (array) config('finisher.payments.openpay');

        if (blank($openpay['merchant_id'] ?? null) || blank($openpay['private_key'] ?? null)) {
            return ['ok' => false, 'message' => 'Faltan el Merchant ID o la llave privada.'];
        }

        try {
            $client = $this->openpay->make(
                (string) $openpay['merchant_id'],
                (string) $openpay['private_key'],
                (string) ($openpay['country'] ?? 'MX'),
                (bool) ($openpay['production_mode'] ?? false),
            );
            $merchant = $client->getMerchantInfo();
        } catch (OpenpayApiError $e) {
            $unauthorized = (int) $e->getHttpCode() === 401 || (int) $e->getErrorCode() === 1002;

            return ['ok' => false, 'message' => $unauthorized
                ? 'OpenPay rechazó las credenciales. Revisa el Merchant ID, la llave privada y el modo (sandbox o producción).'
                : 'OpenPay respondió con un error: '.($e->getDescription() ?: $e->getMessage())];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'No pudimos conectar con OpenPay. Intenta de nuevo en unos minutos.'];
        }

        $row = PaymentSetting::query()->first();

        if ($row !== null) {
            $row->forceFill(['openpay_verified_at' => now()])->save();
            $this->forget();
        }

        $name = is_object($merchant) && isset($merchant->name) ? trim((string) $merchant->name) : '';

        return [
            'ok' => true,
            'message' => $name !== '' ? "Conexión correcta con OpenPay ({$name})." : 'Conexión correcta con OpenPay.',
        ];
    }

    private function privateKey(PaymentSetting $row): ?string
    {
        try {
            $key = $row->getAttribute('openpay_private_key');

            return is_string($key) ? $key : null;
        } catch (DecryptException) {
            // APP_KEY rotated — the saved key can no longer be decrypted.
            return null;
        }
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /**
     * Never the secret itself — only its masked hint.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(PaymentSetting $row): array
    {
        return [
            'default_gateway' => $row->default_gateway,
            'openpay_merchant_id' => $row->openpay_merchant_id,
            'openpay_public_key' => $row->openpay_public_key,
            'openpay_private_key' => filled($row->openpay_private_key_last4) ? '••••'.$row->openpay_private_key_last4 : null,
            'openpay_country' => $row->openpay_country,
            'openpay_production_mode' => $row->openpay_production_mode,
            'openpay_use_3d_secure' => $row->openpay_use_3d_secure,
        ];
    }
}
