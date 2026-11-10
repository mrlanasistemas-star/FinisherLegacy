<?php

namespace App\Support\I18n;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * The `/en` twin of every public page (docs/renovacion/idiomas.md §URLs).
 *
 * Spanish URLs never change. Every other language serves the SAME routes
 * under `/{locale}`: App\Http\Middleware\StripLocalePrefix removes the
 * prefix before routing, so route names, Wayfinder and the route files
 * know nothing about it. This class builds the twin URLs (switcher,
 * hreflang, sitemap) and decides which pages are public enough to have one.
 */
class LocalizedUrls
{
    /** Request attribute set by StripLocalePrefix ("en" for /en/...). */
    public const string PREFIX_ATTRIBUTE = 'i18n.prefix';

    public function __construct(private readonly Request $request) {}

    /** The language prefix the current request arrived with, if any. */
    public function prefix(): ?string
    {
        $prefix = $this->request->attributes->get(self::PREFIX_ATTRIBUTE);

        return is_string($prefix) ? $prefix : null;
    }

    /**
     * The current page is a public GET page that exists in every language:
     * no login required and not one of the excluded paths.
     */
    public function isLocalizable(): bool
    {
        if (! $this->request->isMethod('GET') && ! $this->request->isMethod('HEAD')) {
            return false;
        }

        if (! $this->pathIsLocalizable($this->request->path())) {
            return false;
        }

        $route = $this->request->route();

        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(auth|can|verified|signed|password\.confirm)(:|$)/', $middleware) === 1) {
                return false;
            }
        }

        return in_array('web', $route->gatherMiddleware(), true);
    }

    public function pathIsLocalizable(string $path): bool
    {
        $path = trim($path, '/');

        /** @var list<string> $except */
        $except = config('i18n.prefix.except', []);

        return $path === '' || ! Str::is($except, $path);
    }

    /**
     * The same page in another language. `$url` defaults to the current
     * page; an absolute URL of this site or a path are both accepted.
     */
    public function to(string $locale, ?string $url = null): string
    {
        $root = rtrim($this->request->root(), '/');
        $url ??= $this->request->fullUrl();

        $relative = str_starts_with($url, $root) ? substr($url, strlen($root)) : $url;

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $relative) === 1) {
            return $url; // another site: never rewritten.
        }

        [$path, $query] = array_pad(explode('?', $relative, 2), 2, null);
        $path = '/'.ltrim($this->stripPrefix((string) $path), '/');

        if ($locale !== Locales::source() && Locales::supports($locale) && $this->pathIsLocalizable($path)) {
            $path = '/'.$locale.($path === '/' ? '' : $path);
        }

        return $root.($path === '/' ? '' : $path).($query !== null && $query !== '' ? '?'.$query : '');
    }

    /**
     * hreflang alternates for a page: one per enabled language plus
     * `x-default` (the Spanish URL).
     *
     * @return array<string, string> hreflang => absolute URL
     */
    public function alternates(?string $url = null): array
    {
        $alternates = [];

        foreach (Locales::enabled() as $locale) {
            $alternates[Locales::regional($locale)] = $this->to($locale, $url);
        }

        if (count($alternates) < 2) {
            return [];
        }

        $alternates['x-default'] = $this->to(Locales::source(), $url);

        return $alternates;
    }

    /** `<xhtml:link>` lines for one sitemap `<url>` entry. */
    public function sitemapAlternates(string $url): string
    {
        $xml = '';

        foreach ($this->alternates($url) as $hreflang => $href) {
            $xml .= '<xhtml:link rel="alternate" hreflang="'.e($hreflang).'" href="'.e($href).'"/>';
        }

        return $xml;
    }

    /** Removes a leading `/{locale}` segment of a non-source language. */
    public function stripPrefix(string $path): string
    {
        $segments = explode('/', ltrim($path, '/'), 2);

        if (in_array($segments[0], Locales::targets(), true)) {
            return '/'.($segments[1] ?? '');
        }

        return $path;
    }
}
