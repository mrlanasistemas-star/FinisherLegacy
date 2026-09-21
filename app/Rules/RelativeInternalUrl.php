<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A notification's `action_url` must stay inside this app — never
 * `javascript:`, never an arbitrary external/phishing URL an admin (or a
 * compromised admin session) could plant in front of an athlete
 * (consolidation brief §99-§100). Only a same-origin relative path
 * (`/dashboard/...`) is accepted; an absolute URL, even to this app's own
 * domain, is rejected too — simplest possible allowlist.
 */
class RelativeInternalUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('El enlace no es válido.');

            return;
        }

        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            $fail('El enlace debe ser una ruta interna (por ejemplo /dashboard/legado).');

            return;
        }

        if (str_contains(mb_strtolower($value), ':')) {
            $fail('El enlace debe ser una ruta interna (por ejemplo /dashboard/legado).');
        }
    }
}
