/**
 * Mirror of App\Services\Photos\PhotoFeeCalculator for live previews (same formula).
 */
export type PhotoFeeRules = {
    platform_commission_percent: number;
    processor_fee_percent: number;
    processor_fee_fixed_minor: number;
    processor_fee_vat_percent: number;
    min_price_minor: number;
    max_price_minor: number;
    default_price_minor: number;
};

export function splitPhotoPrice(
    rules: PhotoFeeRules,
    gross: number,
    items = 1,
) {
    const processorBase =
        gross * (rules.processor_fee_percent / 100) +
        rules.processor_fee_fixed_minor / Math.max(items, 1);
    let processor = Math.round(
        processorBase * (1 + rules.processor_fee_vat_percent / 100),
    );
    let platform = Math.round(
        (gross * rules.platform_commission_percent) / 100,
    );
    processor = Math.min(processor, gross);
    platform = Math.min(platform, gross - processor);

    return {
        gross,
        processor,
        platform,
        net: gross - processor - platform,
    };
}
