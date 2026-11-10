<?php

namespace App\Support\I18n;

use App\Models\I18nLocale;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The languages of the site (docs/renovacion/idiomas.md). The list comes
 * from config/i18n.php; an admin can hide one from the public
 * (`i18n_locales`). Spanish, the source, can never be disabled.
 */
class Locales
{
    private const string CACHE_KEY = 'i18n.disabled-locales';

    /** @return array<string, string> code => native name */
    public static function all(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('i18n.locales', ['es' => 'Español']);

        return $locales;
    }

    public static function source(): string
    {
        return (string) config('i18n.source', 'es');
    }

    /** @return list<string> every configured language except the source */
    public static function targets(): array
    {
        return array_values(array_filter(array_keys(self::all()), fn (string $code): bool => $code !== self::source()));
    }

    public static function supports(string $locale): bool
    {
        return array_key_exists($locale, self::all());
    }

    /** @return list<string> languages offered to visitors */
    public static function enabled(): array
    {
        $disabled = self::disabled();

        return array_values(array_filter(
            array_keys(self::all()),
            fn (string $code): bool => $code === self::source() || ! in_array($code, $disabled, true),
        ));
    }

    public static function isEnabled(string $locale): bool
    {
        return in_array($locale, self::enabled(), true);
    }

    public static function setEnabled(string $locale, bool $enabled): void
    {
        if (! self::supports($locale) || $locale === self::source()) {
            return;
        }

        I18nLocale::query()->updateOrCreate(['locale' => $locale], ['enabled' => $enabled]);
        Cache::forget(self::CACHE_KEY);
    }

    /** BCP 47 tag for <html lang>, hreflang and Intl ("es-MX"). */
    public static function regional(string $locale): string
    {
        return (string) config("i18n.regional.{$locale}", $locale);
    }

    /**
     * Maps what a client sent ("en-US", "EN", "en_us") to one of the
     * candidate languages, or null.
     *
     * @param  list<string>|null  $candidates  defaults to the enabled languages
     */
    public static function match(?string $value, ?array $candidates = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $candidates ??= self::enabled();
        $value = strtolower(str_replace('_', '-', trim($value)));

        foreach ([$value, explode('-', $value)[0]] as $attempt) {
            foreach ($candidates as $candidate) {
                if (strtolower($candidate) === $attempt) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Best language for an `Accept-Language` header, by quality.
     *
     * @param  list<string>|null  $candidates
     */
    public static function fromAcceptLanguage(?string $header, ?array $candidates = null): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $ranked = [];

        foreach (explode(',', $header) as $position => $part) {
            $pieces = explode(';', trim($part));
            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (str_starts_with(trim($parameter), 'q=')) {
                    $quality = (float) substr(trim($parameter), 2);
                }
            }

            if ($pieces[0] !== '' && $pieces[0] !== '*') {
                $ranked[] = ['tag' => $pieces[0], 'q' => $quality, 'position' => $position];
            }
        }

        usort($ranked, fn (array $a, array $b): int => [$b['q'], $a['position']] <=> [$a['q'], $b['position']]);

        foreach ($ranked as $entry) {
            if (($match = self::match($entry['tag'], $candidates)) !== null) {
                return $match;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function disabled(): array
    {
        try {
            /** @var list<string> $disabled */
            $disabled = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => I18nLocale::query()->where('enabled', false)->pluck('locale')->all(),
            );

            return $disabled;
        } catch (Throwable) {
            // Table not migrated yet (first deploy): everything is enabled.
            return [];
        }
    }
}
