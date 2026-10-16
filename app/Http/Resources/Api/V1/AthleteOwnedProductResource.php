<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AthleteOwnedProduct;
use App\Support\Nfc\GearItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "Mi equipo" / Digital Closet (brief §91/§111/§115) — visible only to the
 * owning Athlete (or admin), so this is allowed to be fuller than
 * PublicGearResource. Shape lives in App\Support\Nfc\GearItem (shared with
 * /me/gear and the NFC claim response); the original keys are unchanged,
 * NFC/"Producto conectado" fields are additive.
 *
 * @mixin AthleteOwnedProduct
 */
class AthleteOwnedProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AthleteOwnedProduct $owned */
        $owned = $this->resource;

        return GearItem::fromOwnedProduct($owned);
    }
}
