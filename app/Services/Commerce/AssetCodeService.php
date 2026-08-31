<?php

namespace App\Services\Commerce;

use App\Models\AthleteOwnedProduct;
use App\Services\QrCodeService;
use App\Support\CodeGenerator;

/**
 * Generates the unguessable, no-PII identifier a public `/api/v1/gear/{code}`
 * lookup resolves for a QR-capable AthleteOwnedProduct (brief §86-§93/§106-
 * §107/§210). Deliberately separate from LegacyCode/LegacyCodeQrService —
 * Legacy Plate keeps its own QR lifecycle (brief §93), this is for apparel/
 * accessories.
 */
class AssetCodeService
{
    public function __construct(private readonly QrCodeService $qr) {}

    public function generate(): string
    {
        return CodeGenerator::unique(
            'AST',
            fn (string $code) => AthleteOwnedProduct::query()->where('asset_code', $code)->exists(),
            length: 12,
        );
    }

    public function publicUrl(string $assetCode): string
    {
        return route('api.v1.gear.show', $assetCode);
    }

    public function svg(string $assetCode, int $size = 320): string
    {
        return $this->qr->svg($this->publicUrl($assetCode), $size);
    }
}
