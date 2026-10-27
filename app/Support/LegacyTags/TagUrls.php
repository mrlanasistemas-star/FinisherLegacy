<?php

namespace App\Support\LegacyTags;

use App\Models\LegacyTag;

/**
 * The one place the admin builds a tag's public URLs (factory CSV, tag
 * detail): the named `tag.open` route through LegacyTag::nfcUrl()/qrUrl().
 */
final class TagUrls
{
    public static function nfc(LegacyTag $tag): string
    {
        return $tag->nfcUrl();
    }

    public static function qr(LegacyTag $tag): string
    {
        return $tag->qrUrl();
    }
}
