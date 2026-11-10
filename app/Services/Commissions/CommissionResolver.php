<?php

namespace App\Services\Commissions;

use App\Models\PhotographerCommissionSetting;
use App\Models\PhotographerProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Answers "which commission terms apply to this photographer at instant
 * T?" — precedence, highest first:
 *
 *   1. the photographer's override in force at T (a non-null %),
 *   2. the global admin setting in force at T,
 *   3. the env defaults in config('finisher.photos').
 *
 * "In force at T" = latest non-cancelled row with effective_from <= T
 * (ties broken by id). Gateway fee: the admin's OpenPay figures when set
 * on the global row in force, else the env estimate for that provider;
 * a manual (cash/transfer) payment never has a gateway fee.
 */
class CommissionResolver
{
    public function termsFor(PhotographerProfile|int|null $profile, ?CarbonInterface $at = null, ?string $provider = null): CommissionTerms
    {
        $at ??= now();
        $profileId = $profile instanceof PhotographerProfile ? $profile->id : $profile;
        $provider ??= (string) config('finisher.payments.default_gateway', 'openpay');
        $config = config('finisher.photos');

        $global = $this->globalAt($at);
        $override = $profileId !== null ? $this->overrideAt($profileId, $at) : null;

        if ($override !== null && $override->commission_percent !== null) {
            $percent = (float) $override->commission_percent;
            $source = CommissionTerms::SOURCE_OVERRIDE;
            $setting = $override;
        } elseif ($global !== null) {
            $percent = (float) $global->commission_percent;
            $source = CommissionTerms::SOURCE_GLOBAL;
            $setting = $global;
        } else {
            $percent = (float) $config['platform_commission_percent'];
            $source = CommissionTerms::SOURCE_CONFIG;
            $setting = null;
        }

        [$feePercent, $feeFixed, $configured] = $this->gatewayFee($provider, $global);

        return new CommissionTerms(
            commissionPercent: $percent,
            source: $source,
            settingId: $setting?->id,
            effectiveFrom: $setting?->effective_from,
            provider: $provider,
            gatewayFeePercent: $feePercent,
            gatewayFeeFixedMinor: $feeFixed,
            gatewayFeeVatPercent: $provider === 'manual' ? 0.0 : (float) $config['processor_fee_vat_percent'],
            gatewayFeeConfigured: $configured,
            gatewayFeeAbsorbedBy: $this->absorbedBy($global),
        );
    }

    public function globalAt(CarbonInterface $at): ?PhotographerCommissionSetting
    {
        return PhotographerCommissionSetting::query()
            ->active()
            ->where('scope', PhotographerCommissionSetting::SCOPE_GLOBAL)
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function overrideAt(int $profileId, CarbonInterface $at): ?PhotographerCommissionSetting
    {
        return PhotographerCommissionSetting::query()
            ->active()
            ->where('scope', PhotographerCommissionSetting::SCOPE_PHOTOGRAPHER)
            ->where('photographer_profile_id', $profileId)
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The next scheduled change that will alter this photographer's
     * commission %, if any: the earliest future row (global or their own
     * override) whose resulting rate differs from today's.
     *
     * @return array{effective_from: string, commission_percent: float, source: string}|null
     */
    public function upcomingFor(PhotographerProfile|int|null $profile): ?array
    {
        $profileId = $profile instanceof PhotographerProfile ? $profile->id : $profile;
        $current = $this->termsFor($profileId)->commissionPercent;

        $dates = PhotographerCommissionSetting::query()
            ->active()
            ->where('effective_from', '>', now())
            ->where(fn ($q) => $q->where('scope', PhotographerCommissionSetting::SCOPE_GLOBAL)
                ->when($profileId !== null, fn ($w) => $w->orWhere(fn ($o) => $o
                    ->where('scope', PhotographerCommissionSetting::SCOPE_PHOTOGRAPHER)
                    ->where('photographer_profile_id', $profileId))))
            ->orderBy('effective_from')
            ->pluck('effective_from');

        foreach ($dates as $date) {
            $at = Date::parse($date);
            $terms = $this->termsFor($profileId, $at);

            if (abs($terms->commissionPercent - $current) > 0.0001) {
                return [
                    'effective_from' => $at->toIso8601String(),
                    'commission_percent' => $terms->commissionPercent,
                    'source' => $terms->source,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{0: float, 1: int, 2: bool}
     */
    private function gatewayFee(string $provider, ?PhotographerCommissionSetting $global): array
    {
        if ($provider === 'manual') {
            return [0.0, 0, true];
        }

        if ($provider === 'openpay' && $global !== null && $global->gateway_fee_percent !== null) {
            return [(float) $global->gateway_fee_percent, (int) ($global->gateway_fee_fixed_minor ?? 0), true];
        }

        $config = config('finisher.photos');
        $rate = $config['processor_fees'][$provider] ?? null;

        return [
            (float) ($rate['percent'] ?? $config['processor_fee_percent']),
            (int) ($rate['fixed_minor'] ?? $config['processor_fee_fixed_minor']),
            (bool) ($config['gateway_fee_configured'] ?? false),
        ];
    }

    private function absorbedBy(?PhotographerCommissionSetting $global): string
    {
        $value = $global->gateway_fee_absorbed_by ?? config('finisher.photos.gateway_fee_absorbed_by', 'photographer');

        return $value === PhotographerCommissionSetting::ABSORBED_BY_FINISHER
            ? PhotographerCommissionSetting::ABSORBED_BY_FINISHER
            : PhotographerCommissionSetting::ABSORBED_BY_PHOTOGRAPHER;
    }
}
