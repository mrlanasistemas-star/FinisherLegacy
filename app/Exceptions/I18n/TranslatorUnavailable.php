<?php

namespace App\Exceptions\I18n;

use RuntimeException;

/**
 * The translator cannot work at all (missing/invalid key, no credit,
 * unknown model). Aborts the sync run instead of failing string by string.
 */
class TranslatorUnavailable extends RuntimeException {}
