<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';

    /**
     * Suspended/blocked accounts can't sign in, and any session or API
     * token they still hold is rejected (App\Http\Middleware\EnsureUserIsActive).
     */
    public function canSignIn(): bool
    {
        return ! in_array($this, [self::Suspended, self::Blocked], true);
    }

    public function signInDeniedMessage(): string
    {
        return match ($this) {
            self::Blocked => 'Tu cuenta está bloqueada. Si crees que es un error, escríbenos a soporte.',
            default => 'Tu cuenta está suspendida. Si crees que es un error, escríbenos a soporte.',
        };
    }
}
