<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a Legacy story includes without paying, per media kind
 * (App\Enums\StoryQuotaKind): how many photos / videos / voice messages,
 * the max seconds of each video and audio, and an optional hard cap per
 * story. One row per scope — a product, a product type or `global` — and
 * every value is nullable: null inherits from the next scope
 * (App\Services\Media\StoryMediaQuota). Lowering a limit never removes
 * stored content — it only stops new uploads until the story is under it.
 *
 * @property int|null $included_photos
 * @property int|null $included_videos
 * @property int|null $included_audio
 * @property int|null $max_video_seconds
 * @property int|null $max_audio_seconds
 * @property int|null $cap_photos
 * @property int|null $cap_videos
 * @property int|null $cap_audio
 * @property ProductType|null $product_type
 */
#[Fillable([
    'scope_key', 'product_id', 'product_type', 'included_photos', 'included_videos', 'included_audio',
    'max_video_seconds', 'max_audio_seconds', 'cap_photos', 'cap_videos', 'cap_audio', 'notes', 'updated_by',
])]
class PhotoQuotaRule extends Model
{
    /** scope_key of the rule that applies to every story (global default). */
    public const string GLOBAL_KEY = 'global';

    /** Every limit column, in the order the admin form shows them. */
    public const array LIMIT_COLUMNS = [
        'included_photos', 'included_videos', 'included_audio', 'max_video_seconds', 'max_audio_seconds',
        'cap_photos', 'cap_videos', 'cap_audio',
    ];

    protected function casts(): array
    {
        return [
            'included_photos' => 'integer',
            'included_videos' => 'integer',
            'included_audio' => 'integer',
            'max_video_seconds' => 'integer',
            'max_audio_seconds' => 'integer',
            'cap_photos' => 'integer',
            'cap_videos' => 'integer',
            'cap_audio' => 'integer',
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
