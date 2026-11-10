<?php

namespace App\Exceptions\Athletes;

use App\Models\AthleteIdentityConflict;
use RuntimeException;

/**
 * Thrown when a claim (or any identity-sensitive action) discovers the
 * acting User and the target record point at two different Athletes —
 * never auto-merged. See docs/adr/0004-athlete-canonical-identity.md
 * §Claim conflict.
 */
class AthleteIdentityConflictException extends RuntimeException
{
    public function __construct(
        public readonly ?AthleteIdentityConflict $conflict = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('Este código pertenece a una identidad distinta a la de tu cuenta — un administrador debe revisarlo.'));
    }

    public function code(): string
    {
        return 'ATHLETE_IDENTITY_CONFLICT';
    }
}
