<?php

namespace App\Services\Portrait;

use App\Models\LegacyTag;

/**
 * Tells the Portarretrato inteligente apart from a Finisher Legacy Tag.
 * Both are serialized NFC units (LegacyTag rows) with the same activation
 * flow; the only difference is how the story they open presents itself:
 * a frame opens an "álbum" (photo first), a tag a "recuerdo".
 */
final class PortraitProduct
{
    public const string KIND_ALBUM = 'album';

    public const string KIND_TAG = 'tag';

    /**
     * Slugs of the catalog products that are frames (config/portrait.php).
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('portrait.product_slugs', ['portarretrato-inteligente']))));
    }

    /** `album` when the unit's product is a frame, `tag` otherwise. */
    public static function kindFor(?LegacyTag $tag): string
    {
        $slug = $tag?->productVariant?->product?->slug;

        return is_string($slug) && in_array($slug, self::slugs(), true) ? self::KIND_ALBUM : self::KIND_TAG;
    }

    public static function isFrame(?LegacyTag $tag): bool
    {
        return self::kindFor($tag) === self::KIND_ALBUM;
    }
}
