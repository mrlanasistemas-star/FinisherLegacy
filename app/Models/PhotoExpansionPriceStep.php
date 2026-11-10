<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price step ("bandera") of an expansion price schedule: from
 * `from_units` already held by the story on, the pack costs `amount_minor`
 * instead of the schedule's base amount. Written with its schedule and
 * never edited (App\Http\Controllers\Admin\Quotas\ExpansionPriceController);
 * resolved server-side by App\Services\Media\StoryMediaQuota::resolvePrice.
 *
 * @property int $from_units
 * @property int $amount_minor
 * @property string|null $apple_product_id
 * @property string|null $google_product_id
 */
#[Fillable(['product_price_schedule_id', 'from_units', 'amount_minor', 'apple_product_id', 'google_product_id'])]
class PhotoExpansionPriceStep extends Model
{
    protected function casts(): array
    {
        return [
            'from_units' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<ProductPriceSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductPriceSchedule::class, 'product_price_schedule_id');
    }

    public function storeProductId(string $platform): ?string
    {
        $id = $platform === 'ios' ? $this->apple_product_id : ($platform === 'android' ? $this->google_product_id : null);

        return is_string($id) && $id !== '' ? $id : null;
    }
}
