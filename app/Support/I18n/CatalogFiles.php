<?php

namespace App\Support\I18n;

/**
 * Where the generated catalogs live and which version each one is
 * (docs/renovacion/idiomas.md §Archivos generados):
 *
 * - lang/{locale}.json          Laravel's JSON catalog (`__()`): web + backend strings.
 * - lang/web/{locale}.json      what the browser downloads (`/lang/{locale}.json`).
 * - lang/mobile/{locale}.json   what the app downloads (`/api/v1/i18n/{locale}`);
 *                               lang/mobile/es.json is the mobile source list.
 * - {mobile}/i18n/locales/{locale}.json  bundled inside the app.
 *
 * All of them are written by `php artisan i18n:sync`, never by hand.
 */
class CatalogFiles
{
    /** @var array<string, string> */
    private static array $versions = [];

    public static function laravel(string $locale): string
    {
        return lang_path("{$locale}.json");
    }

    public static function web(string $locale): string
    {
        return lang_path("web/{$locale}.json");
    }

    public static function mobile(string $locale): string
    {
        return lang_path("mobile/{$locale}.json");
    }

    /** The mobile repository's `src` folder, or null when it is not on this machine. */
    public static function mobileSourcePath(): ?string
    {
        $path = (string) config('i18n.mobile_path', '');

        if ($path === '') {
            return null;
        }

        $absolute = preg_match('~^([A-Za-z]:[\\\\/]|/)~', $path) === 1 ? $path : base_path($path);
        $real = realpath($absolute);

        return $real !== false && is_dir($real) ? $real : null;
    }

    public static function mobileBundle(string $locale): ?string
    {
        $source = self::mobileSourcePath();

        return $source === null ? null : $source.DIRECTORY_SEPARATOR.'i18n'.DIRECTORY_SEPARATOR.'locales'.DIRECTORY_SEPARATOR."{$locale}.json";
    }

    /** Short content hash of a catalog file; null when it does not exist. */
    public static function version(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $key = $path.'|'.filemtime($path).'|'.filesize($path);

        return self::$versions[$key] ??= substr((string) sha1_file($path), 0, 12);
    }

    /**
     * @return array<string, string> key => translation
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, string> $messages */
        $messages = array_filter($decoded, 'is_string');

        return $messages;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public static function write(string $path, array $messages): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        ksort($messages, SORT_STRING);

        $json = json_encode((object) $messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

        // Untouched files keep their mtime (and their version).
        if (is_file($path) && file_get_contents($path) === $json) {
            return;
        }

        file_put_contents($path, $json, LOCK_EX);
    }
}
