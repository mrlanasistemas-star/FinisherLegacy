<?php

namespace App\Support\Collections;

use App\Enums\ProductType;
use App\Models\Collection;
use App\Models\CollectionDesign;
use App\Models\Product;
use App\Queries\Store\PublicCollections;
use App\Services\Portrait\PortraitProduct;

/**
 * The `tag_design` payload shared by the web (Inertia) and /api/v1:
 * everything a client needs to draw a Finisher Legacy Tag like the deck
 * (pp. 7–8) — artwork, header lines and the back phrase. Never a QR.
 *
 * Shape: { front_art_url, accent, name_line, discipline_line, back_line,
 * monogram_url } — URLs are absolute in /api/v1, root-relative on the web.
 */
final class TagDesign
{
    public const string DEFAULT_BACK_LINE = 'TU HISTORIA SIGUE VIVA';

    public const string MONOGRAM = 'images/brand/logo/fl-monograma.png';

    /**
     * @return array{front_art_url: string|null, accent: string|null, name_line: string, discipline_line: string|null, back_line: string, monogram_url: string}
     */
    public static function forCollection(Collection $collection, ?CollectionDesign $design = null, bool $absolute = true): array
    {
        return [
            'front_art_url' => self::absoluteUrl($design?->front_art_path ?: $collection->tag_front_art, $absolute),
            'accent' => PublicCollections::safeColor($design?->accent ?: ($collection->tag_accent ?: $collection->accent_color)),
            'name_line' => self::nameLine($collection),
            'discipline_line' => self::disciplineLine($collection),
            'back_line' => filled($collection->tag_back_line) ? self::upper((string) tr($collection, 'tag_back_line')) : self::defaultBackLine(),
            'monogram_url' => self::monogramUrl($absolute),
        ];
    }

    /**
     * Every design of a collection, primary first (the hero and the
     * collection page rotate through them).
     *
     * @return list<array<string, mixed>>
     */
    public static function allForCollection(Collection $collection, bool $absolute = true): array
    {
        $designs = [['slug' => 'principal', 'name' => tr($collection, 'name'), ...self::forCollection($collection, null, $absolute)]];

        foreach ($collection->designs as $design) {
            $designs[] = ['slug' => $design->slug, 'name' => tr($design, 'name'), ...self::forCollection($collection, $design, $absolute)];
        }

        return $designs;
    }

    /**
     * A tag product's design: its own collection design, else the
     * collection's primary one. Null for products that are not tags —
     * including the Portarretrato inteligente, which is serialized like a
     * tag (`tracks_units`) but is a frame: clients show its photos.
     *
     * @return array<string, string|null>|null
     */
    public static function forProduct(Product $product, bool $absolute = true): ?array
    {
        if (PortraitProduct::isFrameProduct($product)) {
            return null;
        }

        if ($product->type !== ProductType::LegacyTag && ! $product->tracks_units) {
            return null;
        }

        $collection = $product->collection;

        if ($collection !== null) {
            return self::forCollection($collection, $product->design, $absolute);
        }

        return [
            'front_art_url' => null,
            'accent' => null,
            'name_line' => self::upper((string) tr($product, 'name')),
            'discipline_line' => null,
            'back_line' => self::defaultBackLine(),
            'monogram_url' => self::monogramUrl($absolute),
        ];
    }

    /** "VERACRUZ" — the destination, or the name without a trailing year. */
    public static function nameLine(Collection $collection): string
    {
        $name = trim((string) ($collection->destination ?: tr($collection, 'name')));
        $year = $collection->year !== null ? (string) $collection->year : null;

        if ($year !== null && str_ends_with($name, $year)) {
            $name = trim(substr($name, 0, -strlen($year)), " \t-—·") ?: $name;
        }

        return self::upper($name !== '' ? $name : 'FINISHER LEGACY');
    }

    /** "TRIATLÓN 2026": the admin's line, else discipline + year. */
    public static function disciplineLine(Collection $collection): ?string
    {
        if (filled($collection->tag_discipline_line)) {
            return self::upper((string) tr($collection, 'tag_discipline_line'));
        }

        $parts = array_filter([tr($collection, 'discipline'), $collection->year], fn ($part) => filled($part));

        return $parts === [] ? null : self::upper(implode(' ', $parts));
    }

    /**
     * Public-disk path, /public path or URL → absolute URL (API). With
     * `$absolute = false` (web pages) anything this app serves becomes a
     * root-relative path, so the browser loads it same-origin whatever
     * host alias it used (WebGL textures need CORS-clean images).
     */
    public static function absoluteUrl(?string $path, bool $absolute = true): ?string
    {
        $url = Collection::assetUrl($path);

        if ($url === null) {
            return null;
        }

        if ($absolute) {
            return url($url);
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' && str_starts_with($url, $appUrl.'/') ? substr($url, strlen($appUrl)) : $url;
    }

    /** The default back phrase, in the language of the request. */
    public static function defaultBackLine(): string
    {
        return self::upper(__('TU HISTORIA SIGUE VIVA'));
    }

    public static function monogramUrl(bool $absolute = true): string
    {
        return $absolute ? asset(self::MONOGRAM) : '/'.self::MONOGRAM;
    }

    private static function upper(string $text): string
    {
        return mb_strtoupper(trim($text), 'UTF-8');
    }
}
