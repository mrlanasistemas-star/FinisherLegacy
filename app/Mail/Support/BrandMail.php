<?php

namespace App\Mail\Support;

use App\Models\CompanySetting;
use Throwable;

/**
 * Shared bits of the Finisher Legacy email identity (brand manual: Blanco
 * Hueso background, white card, Negro Carbono text, Oro Legacy accents):
 * absolute logo/signature URLs, support email and legal links. Used by the
 * branded layout in resources/views/emails/** and by the overridden Laravel
 * mail theme in resources/views/vendor/mail/**, so every email — our own
 * Mailables and framework notifications (verificar correo, restablecer
 * contraseña…) — carries the same header and footer.
 */
final class BrandMail
{
    public const string BONE = '#F8F7F3';

    public const string CARBON = '#0B0B0C';

    public const string GOLD = '#C9AE8B';

    public const string FONT_STACK = "Montserrat, Manrope, 'Helvetica Neue', Arial, sans-serif";

    private const string FALLBACK_SUPPORT_EMAIL = 'hola@finisherlegacy.com';

    public static function logoUrl(): string
    {
        return asset('images/brand/logo/fl-firma-horizontal-claro.png');
    }

    public static function signatureUrl(): string
    {
        return asset('images/brand/logo/fl-firma-make-it-last.png');
    }

    /** Fallback thumbnail for a line with no product image (e.g. a digital item). */
    public static function monogramUrl(): string
    {
        return asset('images/brand/logo/fl-monograma.png');
    }

    public static function homeUrl(): string
    {
        return url('/');
    }

    public static function privacyUrl(): string
    {
        return route('privacy');
    }

    public static function termsUrl(): string
    {
        return route('terms');
    }

    /** Contact email an admin set in Empresa → Contacto, else the public default. */
    public static function supportEmail(): string
    {
        try {
            $email = CompanySetting::contactChannels()['email'] ?? null;
        } catch (Throwable) {
            $email = null;
        }

        return filled($email) ? (string) $email : self::FALLBACK_SUPPORT_EMAIL;
    }

    /** Absolute URL for an image that may come back root-relative from a disk. */
    public static function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }
}
