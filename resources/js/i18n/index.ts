/**
 * Idiomas del sitio (docs/renovacion/idiomas.md §Web) — a tiny,
 * dependency-free translator.
 *
 * Spanish is the source of truth and the Spanish text IS the key:
 *
 *     t('Tu esfuerzo merece una historia')
 *     t('Hola, {name}', { name: user.first_name })
 *     tc('{count} foto|{count} fotos', photos.length)
 *     <h1>{{ $t('Colecciones') }}</h1>          (templates, no import)
 *     <T>Compra tu Tag</T>                      (components/i18n/T.vue)
 *     t('Nombre||nombre completo del atleta')   (translator note, never shown)
 *     <I18nT :text="$t('¿Ya tienes cuenta? {link}')">   (components/i18n/I18nT.vue)
 *
 * In Spanish nothing is loaded: `t()` returns its argument. In any other
 * language the catalog `/lang/{locale}.json?v={version}` is fetched once
 * (before the app mounts, so there is no flash of Spanish) and a string
 * that is not translated yet falls back to the Spanish original.
 *
 * `php artisan i18n:sync` finds these calls — only LITERAL strings: never
 * `t(variable)` or a template string with `${…}`; use `{placeholders}`.
 *
 * Inside components prefer `const { t } = useI18n()` or `$t` in the
 * template: they are bound to the app instance (correct under SSR too).
 * The module-level `t` / `tc` are for plain TypeScript run in the browser
 * (handlers, toasts, lib code).
 */
import { router, usePage } from '@inertiajs/vue3';
import type { App, InjectionKey } from 'vue';
import { computed, inject, reactive } from 'vue';
import { update as updateLocale } from '@/routes/locale';
import { formatMoney, ordinal, stripNote } from './text';

export { formatMoney, ordinal, splitMarkers, stripNote } from './text';
export type { Segment } from './text';

export type Messages = Record<string, string>;
export type Params = Record<string, string | number>;

export interface LocaleOption {
    /** "en" */
    code: string;
    /** Native name: "English" */
    name: string;
    /** BCP 47 tag for Intl: "en-US" */
    regional: string;
    /** This same page in that language. */
    url: string;
}

interface SharedI18nProps {
    locale?: string;
    locales?: LocaleOption[];
    i18n?: { source: string; regional: string; version: string | null };
}

export interface I18n {
    /** Active language code ("es", "en"). Reactive. */
    readonly locale: string;
    /** The language the strings are written in ("es"). */
    readonly source: string;
    /** BCP 47 tag of the active language, for Intl ("en-US"). */
    readonly regional: string;
    t: (source: string, params?: Params) => string;
    tc: (source: string, count: number, params?: Params) => string;
    /** A place: "3.º" in Spanish, "3rd" in English. */
    ordinal: (value: number) => string;
    /** Cents → "$1,200.00 MXN" with the separators of the active language. */
    money: (minor: number, currency?: string) => string;
}

interface State {
    locale: string;
    source: string;
    regional: string;
    version: string | null;
    messages: Messages;
}

const KEY: InjectionKey<I18n> = Symbol('fl-i18n');

/** Catalogs already downloaded in this tab, by "locale@version". */
const cache = new Map<string, Messages>();

// Server-side rendering cannot fetch: the SSR bundle carries the catalogs
// that existed when it was built. Stripped from the browser bundle.
const bundled: Record<string, Messages> = import.meta.env.SSR
    ? import.meta.glob<Messages>('../../../lang/web/*.json', {
          eager: true,
          import: 'default',
      })
    : {};

/** Replaces `{name}` and `:name` placeholders. */
export function interpolate(text: string, params?: Params): string {
    if (!params) {
        return text;
    }

    // No lookbehind on purpose: older iOS Safari cannot parse it.
    return text.replace(
        /\{\s*([A-Za-z_][\w.]*)\s*\}|(^|[^\w:]):([A-Za-z_]\w*)/g,
        (
            match,
            braced: string | undefined,
            before: string | undefined,
            colon: string | undefined,
        ) => {
            const value = params[braced ?? colon ?? ''];

            return value === undefined
                ? match
                : `${braced === undefined ? (before ?? '') : ''}${value}`;
        },
    );
}

/** Picks the form of a `singular|plural` string for a count. */
export function pluralize(
    text: string,
    count: number,
    regional: string,
): string {
    const forms = text.split('|');

    if (forms.length === 1) {
        return text;
    }

    let one = count === 1;

    try {
        one = new Intl.PluralRules(regional).select(count) === 'one';
    } catch {
        // Unknown locale tag: keep the simple rule.
    }

    return (one ? forms[0] : forms[forms.length - 1]).trim();
}

function createI18n(state: State): I18n {
    // `||` starts a translator note: part of the key, never of the text.
    const lookup = (source: string): string =>
        stripNote(
            state.locale === state.source
                ? source
                : state.messages[source] || source,
        );

    return {
        get locale() {
            return state.locale;
        },
        get source() {
            return state.source;
        },
        get regional() {
            return state.regional;
        },
        t: (source, params) => interpolate(lookup(source), params),
        tc: (source, count, params) =>
            interpolate(pluralize(lookup(source), count, state.regional), {
                count,
                ...params,
            }),
        ordinal: (value) => ordinal(value, state.regional),
        money: (minor, currency = 'MXN') =>
            formatMoney(minor, currency, state.regional),
    };
}

function newState(): State {
    return reactive<State>({
        locale: 'es',
        source: 'es',
        regional: 'es-MX',
        version: null,
        messages: {},
    });
}

// The browser has ONE app: this is its state. (SSR builds one per request.)
const browserState = newState();
const browser = createI18n(browserState);

/** Translates a Spanish source string into the active language. */
export const t: I18n['t'] = (source, params) => browser.t(source, params);

/** Same, choosing between `singular|plural` by `count` (also `{count}`). */
export const tc: I18n['tc'] = (source, count, params) =>
    browser.tc(source, count, params);

/**
 * BCP 47 tag of the active language ("es-MX", "en-US") for `Intl` in plain
 * TS that only runs in the browser (`lib/`); components use
 * `useI18n().regional`.
 */
export function activeRegional(): string {
    return browser.regional;
}

async function fetchCatalog(url: string, timeoutMs = 4000): Promise<Messages> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return {};
        }

        const body = (await response.json()) as { messages?: Messages };

        return body.messages ?? {};
    } catch {
        // Offline or slow: the page simply shows the Spanish originals.
        return {};
    } finally {
        clearTimeout(timer);
    }
}

async function activate(
    locale: string,
    version: string | null,
    regional: string,
    url?: string,
): Promise<void> {
    let messages: Messages = {};

    if (version !== null && locale !== browserState.source) {
        const key = `${locale}@${version}`;

        messages =
            cache.get(key) ??
            (await fetchCatalog(
                url ?? `/lang/${locale}.json?v=${encodeURIComponent(version)}`,
            ));
        cache.set(key, messages);
    }

    browserState.messages = messages;
    browserState.version = version;
    browserState.regional = regional;
    browserState.locale = locale;
}

/**
 * Call (and await) once before `createInertiaApp`: reads the language the
 * server rendered (`<meta name="fl-locale">`, resources/views/i18n/head.blade.php)
 * and downloads its catalog. Resolves immediately in Spanish and on the
 * server; never rejects and never blocks longer than the fetch timeout.
 */
export async function initializeI18n(): Promise<void> {
    if (typeof document === 'undefined') {
        return;
    }

    const meta = (name: string): string | null =>
        document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)
            ?.content ?? null;

    const locale = meta('fl-locale') ?? document.documentElement.lang ?? 'es';
    const catalog = meta('fl-catalog');
    const version = catalog
        ? new URL(catalog, window.location.href).searchParams.get('v')
        : null;

    await activate(
        locale,
        version,
        document.documentElement.lang || locale,
        catalog ?? undefined,
    );

    // Inertia visits that land on another language (a /en link, the
    // browser's back button) swap the catalog without a reload.
    router.on('navigate', (event) => {
        const props = event.detail.page.props as SharedI18nProps;
        const next = props.locale ?? browserState.locale;
        const nextVersion = props.i18n?.version ?? null;

        if (
            next !== browserState.locale ||
            nextVersion !== browserState.version
        ) {
            void activate(next, nextVersion, props.i18n?.regional ?? next).then(
                () => {
                    document.documentElement.lang = next;
                },
            );
        }
    });
}

/**
 * `withApp` hook of `createInertiaApp`: gives the app `$t` / `$tc` in every
 * template and the instance `useI18n()` returns. On the server each
 * request gets its own instance, built from that page's props.
 */
export function installI18n(
    app: App,
    options: { ssr: boolean; page: { props: unknown } },
): void {
    let instance = browser;

    if (options.ssr) {
        const props = options.page.props as SharedI18nProps;
        const locale = props.locale ?? 'es';
        const state = newState();

        state.locale = locale;
        state.source = props.i18n?.source ?? 'es';
        state.regional = props.i18n?.regional ?? locale;
        state.messages = bundled[`../../../lang/web/${locale}.json`] ?? {};
        instance = createI18n(state);
    } else {
        // Same regional tag the server rendered with ("es-MX", not the bare
        // <html lang="es">), or Intl output — prices, dates — would differ
        // after hydration ("$400.00" vs "400,00 MXN").
        const regional = (options.page.props as SharedI18nProps).i18n?.regional;

        if (regional) {
            browserState.regional = regional;
        }
    }

    app.provide(KEY, instance);
    app.config.globalProperties.$t = instance.t;
    app.config.globalProperties.$tc = instance.tc;
}

/**
 * The translator plus everything a component needs about languages:
 *
 *     const { t, tc, ordinal, money, locale, locales, switchLocale, localizePath } = useI18n();
 */
export function useI18n() {
    const i18n = inject(KEY, browser);
    const page = usePage();

    /** Languages offered to the public, each with this page's URL in it. */
    const locales = computed<LocaleOption[]>(
        () => (page.props as SharedI18nProps).locales ?? [],
    );

    const locale = computed(() => i18n.locale);
    const regional = computed(() => i18n.regional);

    /**
     * Changes the language: remembers it (cookie + account) and reloads
     * this same page in that language.
     */
    function switchLocale(code: string): void {
        if (code === i18n.locale) {
            return;
        }

        router.post(updateLocale().url, {
            locale: code,
            url: window.location.href,
        });
    }

    /**
     * An internal path in the active language: `/colecciones` →
     * `/en/colecciones`. Optional — plain links work too (the server sends
     * people who chose a language to the right twin) — but it saves that
     * redirect on public pages.
     */
    function localizePath(path: string): string {
        if (
            i18n.locale === i18n.source ||
            !path.startsWith('/') ||
            path.startsWith('//')
        ) {
            return path;
        }

        const prefix = `/${i18n.locale}`;

        if (path === prefix || path.startsWith(`${prefix}/`)) {
            return path;
        }

        return path === '/' ? prefix : `${prefix}${path}`;
    }

    return {
        t: i18n.t,
        tc: i18n.tc,
        ordinal: i18n.ordinal,
        money: i18n.money,
        locale,
        regional,
        locales,
        switchLocale,
        localizePath,
    };
}

declare module 'vue' {
    interface ComponentCustomProperties {
        /** Translates a Spanish source string (see resources/js/i18n). */
        $t: I18n['t'];
        /** Plural-aware `$t`: `$tc('{count} foto|{count} fotos', n)`. */
        $tc: I18n['tc'];
    }
}
