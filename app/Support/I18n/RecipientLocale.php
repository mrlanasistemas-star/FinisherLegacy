<?php

namespace App\Support\I18n;

use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;

/**
 * Renders text for someone who is NOT the person making the request
 * (docs/renovacion/idiomas.md §Correos, notificaciones y push): a push or
 * in-app notification, an e-mail built in a command, a message to another
 * user. Inside the callback `__()`, `trans_choice()` and Carbon's translated
 * formats use the recipient's language; the previous one is restored after.
 *
 *     RecipientLocale::render($user, fn () => __('Tu pedido :number va en camino', ['number' => $n]));
 *
 * A recipient with no saved (or no longer offered) language gets the
 * application default — never the sender's or the worker's language.
 */
final class RecipientLocale
{
    /**
     * The language to write to `$recipient` in: a User (or anything with a
     * locale preference), a locale code, or null.
     */
    public static function of(mixed $recipient): string
    {
        $wanted = match (true) {
            $recipient instanceof HasLocalePreference => $recipient->preferredLocale(),
            is_string($recipient) => $recipient,
            default => null,
        };

        return Locales::match($wanted) ?? self::fallback();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function render(mixed $recipient, Closure $callback): mixed
    {
        $locale = self::of($recipient);
        $previous = app()->getLocale();

        if ($locale === $previous) {
            return $callback();
        }

        // Carbon follows the application locale (its service provider
        // listens to LocaleUpdated), so dates are covered by this switch.
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * A string as is, or a `fn () => __('…')` rendered in the recipient's
     * language — the shape SendAthleteNotification accepts for title/body.
     *
     * @param  string|Closure(): string  $text
     */
    public static function text(mixed $recipient, string|Closure $text): string
    {
        return $text instanceof Closure ? (string) self::render($recipient, $text) : $text;
    }

    private static function fallback(): string
    {
        $default = (string) config('i18n.default');

        return Locales::supports($default) ? $default : Locales::source();
    }
}
