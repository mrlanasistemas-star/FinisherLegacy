<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A correctly signed provider event this app deliberately doesn't act on
 * (e.g. `customer.created`), or one for a payment it never created. The
 * webhook controller acknowledges it with a 2xx so the provider stops
 * retrying — never a 500 loop, never a state change.
 */
class UnhandledPaymentWebhookEventException extends RuntimeException {}
