<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Base photos included in a Legacy story, per product or per product type
 * (App\Services\Media\StoryPhotoQuota). Lowering it never removes stored
 * photos — it only stops new uploads until the story is under the limit.
 *
 * @property int $included_photos
 * @property ProductType|null $product_type
 */
#[Fillable(['scope_key', 'product_id', 'product_type', 'included_photos', 'notes', 'updated_by'])]
class PhotoQuotaRule extends Model
{
    protected function casts(): array
    {
        return [
            'included_photos' => 'integer',
            'product_type' => ProductType::class,
        ];
    }

    public static function productKey(int $productId): string
    {
        return 'product:'.$productId;
    }

    public static function typeKey(ProductType $type): string
    {
        return 'type:'.$type->value;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
