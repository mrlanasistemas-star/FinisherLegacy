<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row behind /admin/configuracion/pagos. Read it through
 * App\Services\Commerce\Payments\PaymentSettings, never directly — that
 * service owns caching and the "DB overrides .env" merge.
 *
 * `openpay_private_key` is encrypted at rest (APP_KEY) and hidden from
 * serialization; only its last four characters are kept in clear so the
 * admin UI can say "••••1234 configurada".
 *
 * @property int $id
 * @property string|null $default_gateway
 * @property string|null $openpay_merchant_id
 * @property string|null $openpay_public_key
 * @property string|null $openpay_private_key
 * @property string|null $openpay_private_key_last4
 * @property string|null $openpay_country
 * @property bool|null $openpay_production_mode
 * @property bool $openpay_use_3d_secure
 * @property Carbon|null $openpay_verified_at
 * @property int|null $updated_by
 */
#[Fillable([
    'default_gateway', 'openpay_merchant_id', 'openpay_public_key', 'openpay_private_key',
    'openpay_private_key_last4', 'openpay_country', 'openpay_production_mode', 'openpay_use_3d_secure',
    'openpay_verified_at', 'updated_by',
])]
#[Hidden(['openpay_private_key'])]
class PaymentSetting extends Model
{
    protected function casts(): array
    {
        return [
            'openpay_private_key' => 'encrypted',
            'openpay_production_mode' => 'boolean',
            'openpay_use_3d_secure' => 'boolean',
            'openpay_verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
