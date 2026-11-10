/**
 * Mirror of App\Services\Commissions\CommissionCalculator for live previews
 * (same formula, same rounding). The rules come from
 * App\Services\Photos\PhotoFeeCalculator::rules() — the photographer's own
 * terms (override → global → env) for the default web gateway.
 */
export type PhotoFeeRules = {
    platform_commission_percent: number;
    processor_fee_percent: number;
    processor_fee_fixed_minor: number;
    processor_fee_vat_percent: number;
    min_price_minor: number;
    max_price_minor: number;
    default_price_minor: number;
    commission_source?: 'config' | 'global' | 'override';
    commission_effective_from?: string | null;
    gateway_fee_configured?: boolean;
    gateway_fee_absorbed_by?: 'photographer' | 'finisher';
    upcoming_change?: {
        effective_from: string;
        commission_percent: number;
        source: string;
    } | null;
};

export function splitPhotoPrice(
    rules: PhotoFeeRules,
    gross: number,
    items = 1,
) {
    gross = Math.max(gross, 0);
    const processorBase =
        gross * (rules.processor_fee_percent / 100) +
        rules.processor_fee_fixed_minor / Math.max(items, 1);
    const processor = Math.min(
        Math.round(processorBase * (1 + rules.processor_fee_vat_percent / 100)),
        gross,
    );
    let platform = Math.round(
        (gross * rules.platform_commission_percent) / 100,
    );
    const finisherAbsorbs = rules.gateway_fee_absorbed_by === 'finisher';

    platform = Math.min(platform, finisherAbsorbs ? gross : gross - processor);

    return {
        gross,
        processor,
        platform,
        finisherAbsorbs,
        net: finisherAbsorbs ? gross - platform : gross - processor - platform,
    };
}
