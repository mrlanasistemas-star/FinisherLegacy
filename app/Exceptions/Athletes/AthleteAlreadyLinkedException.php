<?php

namespace App\Exceptions\Athletes;

use RuntimeException;

/**
 * A User can have at most one Athlete — thrown when something tries to
 * link a second one instead of reusing the existing link.
 */
class AthleteAlreadyLinkedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('Esta cuenta ya está vinculada a un atleta distinto.'));
    }

    public function code(): string
    {
        return 'ATHLETE_ALREADY_LINKED';
    }
}
